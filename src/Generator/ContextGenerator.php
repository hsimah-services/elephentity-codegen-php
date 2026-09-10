<?php

declare(strict_types=1);

namespace Eleph\Gen\Php\Generator;

use Eleph\Gen\Php\GeneratedFile;
use Eleph\Gen\Php\Ir\Cardinality;
use Eleph\Gen\Php\Ir\EdgeDefinition;
use Eleph\Gen\Php\Ir\EntityDefinition;
use Eleph\Gen\Php\Ir\FieldDefinition;
use Eleph\Gen\Php\Naming\Emitter;
use Eleph\Gen\Php\Naming\Names;
use Eleph\Gen\Php\Naming\TypeMapper;
use Eleph\Gen\Php\Runtime;
use Nette\PhpGenerator\ClassType;

/**
 * Emits the two kinds of context: an action's narrow write surface, and an entity's
 * typed view of a pending mutation.
 */
final readonly class ContextGenerator
{
    private const SCALARS = ['string', 'int', 'float', 'bool', 'array'];

    public function __construct(
        private Names $names,
        private TypeMapper $types,
        private Emitter $emitter,
    ) {
    }

    /**
     * One class per action, exposing only its declared writes.
     *
     * This is what turns "blast radius" from a comment into something the compiler
     * checks: a method that is not generated cannot be called.
     *
     * @return list<GeneratedFile>
     */
    public function actionContexts(EntityDefinition $entity): array
    {
        $files = [];

        foreach ($entity->actions as $action) {
            $class = $this->names->actionContext($entity, $action->name);
            $namespace = $this->emitter->open($class);
            $namespace->addUse(Runtime::MUTATION_BUFFER);

            $type = $namespace->addClass($this->emitter->shortName($class));
            $type->setFinal();
            $type->addComment(sprintf(
                'Everything %s::%s() is allowed to write, and nothing else.',
                $entity->name,
                $action->name,
            ));

            $constructor = $type->addMethod('__construct');
            $constructor->addPromotedParameter('buffer')
                ->setType(Runtime::MUTATION_BUFFER)
                ->setPrivate()
                ->setReadOnly();

            foreach ($action->writes->fields as $name) {
                $field = $entity->field($name);

                if (null === $field) {
                    continue;
                }

                $phpType = $this->types->forField($entity, $field);

                if (!in_array($phpType, self::SCALARS, true)) {
                    $namespace->addUse($phpType);
                }

                $setter = $type->addMethod($this->names->setter($name))
                    ->setReturnType('self')
                    ->setBody(sprintf(
                        "\$this->buffer->set(%s, \$%s);\n\nreturn \$this;",
                        var_export($name, true),
                        $name,
                    ));

                $setter->addParameter($name)->setType($phpType)->setNullable($field->nullable);
            }

            if ([] !== $action->writes->edges) {
                $namespace->addUse(Runtime::EDGE_MUTATION);
            }

            foreach ($action->writes->edges as $name) {
                $type->addMethod($name)
                    ->setReturnType(Runtime::EDGE_MUTATION)
                    ->setBody(sprintf('return $this->buffer->edge(%s);', var_export($name, true)));
            }

            $this->emitter->namedConstructor($type, $constructor);

            $files[] = $this->emitter->file($class, $namespace);
        }

        return $files;
    }

    /**
     * An entity's mutation context, typed.
     *
     * Shared type processors take the generic MutationContext and its stringly-typed
     * accessors, because Money is used by many entities and cannot accept a per-entity
     * class. Field verifiers and triggers get this instead, where every accessor is
     * exact.
     */
    public function mutationContext(EntityDefinition $entity): GeneratedFile
    {
        $class = $this->names->mutationContext($entity);
        $namespace = $this->emitter->open($class);
        $namespace->addUse(Runtime::MUTATION_CONTEXT);

        $type = $namespace->addClass($this->emitter->shortName($class));
        $type->setFinal();
        $type->setReadOnly();
        $type->addImplement(Runtime::MUTATION_CONTEXT);
        $type->addComment(sprintf('A pending %s mutation, with exact types.', $entity->name));
        $namespace->addUse(Runtime::IDENTIFIER);

        $constructor = $type->addMethod('__construct');
        $constructor->addPromotedParameter('context')
            ->setType(Runtime::MUTATION_CONTEXT)
            ->setPrivate();

        $type->addMethod('id')
            ->setReturnType(Runtime::IDENTIFIER)
            ->setBody('return $this->context->id();');

        foreach (['entity' => 'string', 'isCreate' => 'bool'] as $method => $returns) {
            $type->addMethod($method)
                ->setReturnType($returns)
                ->setBody(sprintf('return $this->context->%s();', $method));
        }

        foreach (['original', 'pending'] as $method) {
            $delegate = $type->addMethod($method)
                ->setReturnType('mixed')
                ->setBody(sprintf('return $this->context->%s($field);', $method));
            $delegate->addParameter('field')->setType('string');
        }

        $changed = $type->addMethod('isChanged')
            ->setReturnType('bool')
            ->setBody('return $this->context->isChanged($field);');
        $changed->addParameter('field')->setType('string');

        $type->addMethod('changes')
            ->setReturnType('array')
            ->setBody('return $this->context->changes();')
            ->addComment('@return array<string, mixed>');

        $pendingEdge = $type->addMethod('pendingEdge')
            ->setReturnType('array')
            ->setBody('return $this->context->pendingEdge($edge);')
            ->addComment('@return list<' . $this->emitter->shortName(Runtime::IDENTIFIER) . '>');
        $pendingEdge->addParameter('edge')->setType('string');

        $edgeChanged = $type->addMethod('isEdgeChanged')
            ->setReturnType('bool')
            ->setBody('return $this->context->isEdgeChanged($edge);');
        $edgeChanged->addParameter('edge')->setType('string');

        foreach ($entity->fields as $field) {
            $phpType = $this->types->forField($entity, $field);

            if (!in_array($phpType, self::SCALARS, true)) {
                $namespace->addUse($phpType);
            }

            foreach (['original', 'pending'] as $side) {
                $type->addMethod($side . ucfirst($field->name))
                    ->setReturnType($phpType)
                    // Always nullable: there is no original on create, and a pending
                    // value can legitimately be null.
                    ->setReturnNullable(true)
                    ->setBody($this->narrowingBody($side, $field, $phpType));
            }
        }

        // Unconditional: pendingEdge()'s own docblock names Identifier even on an
        // entity with no edges of its own, because it exists to satisfy the interface.
        $namespace->addUse(Runtime::IDENTIFIER);

        foreach ($entity->edges as $edge) {
            $this->addEdgeAccessors($type, $edge);
        }

        $this->emitter->namedConstructor($type, $constructor);

        return $this->emitter->file($class, $namespace);
    }

    /**
     * Typed per-edge reads, mirroring the mutator's own to-one/to-many split: a
     * to-one edge narrows to at most one Identifier, a to-many edge stays a list. The
     * underlying context is cardinality-agnostic (pendingEdge() always returns a
     * list), so narrowing to one is this generator's job, same as a field's scalar
     * assert() is.
     */
    private function addEdgeAccessors(ClassType $type, EdgeDefinition $edge): void
    {
        $pendingName = 'pending' . ucfirst($edge->name);

        if (Cardinality::One === $edge->cardinality) {
            $type->addMethod($pendingName)
                ->setReturnType(Runtime::IDENTIFIER)
                ->setReturnNullable(true)
                ->setBody(sprintf(
                    'return $this->context->pendingEdge(%s)[0] ?? null;',
                    var_export($edge->name, true),
                ));
        } else {
            $type->addMethod($pendingName)
                ->setReturnType('array')
                ->setBody(sprintf('return $this->context->pendingEdge(%s);', var_export($edge->name, true)))
                ->addComment('@return list<' . $this->emitter->shortName(Runtime::IDENTIFIER) . '>');
        }

        $type->addMethod('is' . ucfirst($edge->name) . 'Changed')
            ->setReturnType('bool')
            ->setBody(sprintf('return $this->context->isEdgeChanged(%s);', var_export($edge->name, true)));
    }

    /**
     * The generic context returns mixed, so each typed accessor asserts its own type.
     *
     * assert() is the right tool: PHPStan narrows on it, and in production it compiles
     * away, so exact typing costs nothing at runtime.
     */
    private function narrowingBody(string $side, FieldDefinition $field, string $phpType): string
    {
        $check = match ($phpType) {
            'string' => 'is_string($value)',
            'int' => 'is_int($value)',
            'float' => 'is_float($value)',
            'bool' => 'is_bool($value)',
            'array' => 'is_array($value)',
            default => sprintf('$value instanceof %s', $this->emitter->shortName($phpType)),
        };

        return sprintf(
            "\$value = \$this->context->%s(%s);\nassert(null === \$value || %s);\n\nreturn \$value;",
            $side,
            var_export($field->name, true),
            $check,
        );
    }
}
