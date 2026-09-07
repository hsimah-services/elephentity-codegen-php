<?php

declare(strict_types=1);

namespace Eleph\Gen\Php;

/**
 * The runtime classes generated code refers to, as names rather than as imports.
 *
 * Generated PHP implements `Hydrator`, returns an `EntityId` and takes a
 * `MutationBuffer`, so the generator has to write those names into its output. It does
 * not have to be able to *load* them, and it should not: requiring
 * `elephentity/runtime` to emit the string "Eleph\Runtime\Query\Hydrator" would make a
 * builder depend on the framework it generates for, which is the coupling that stops a
 * builder being pluggable at all. A TypeScript builder could never satisfy it.
 *
 * They are also what a rewrite in another language will hold — a Rust generator has no
 * PHP classes to import either, only strings to emit — so this file is the shape the
 * next implementation copies.
 *
 * The runtime is the contract here: if one of these is renamed, generated code stops
 * compiling in the project that uses it, and no amount of type checking on this side
 * would have caught it. What catches it is regenerating the worked example.
 */
final class Runtime
{
    public const DELETION = 'Eleph\Runtime\Mutation\Deletion';
    public const DELETION_POLICY = 'Eleph\Runtime\Storage\DeletionPolicy';
    public const DELETION_RULE = 'Eleph\Runtime\Storage\DeletionRule';
    public const EDGE_LOADER = 'Eleph\Runtime\Query\EdgeLoader';
    public const EDGE_MUTATION = 'Eleph\Runtime\Mutation\EdgeMutation';
    public const ENTITY_CATALOGUE = 'Eleph\Runtime\Catalogue\EntityCatalogue';
    public const ENTITY_ID = 'Eleph\Runtime\Identity\EntityId';
    public const ENTITY_QUERY = 'Eleph\Runtime\Query\EntityQuery';
    public const ENTITY_TRIGGERS = 'Eleph\Runtime\Mutation\EntityTriggers';
    public const ENTITY_VERIFIERS = 'Eleph\Runtime\Verification\EntityVerifiers';
    public const HYDRATOR = 'Eleph\Runtime\Query\Hydrator';
    public const IDENTIFIER = 'Eleph\Runtime\Identity\Identifier';
    public const MANAGED = 'Eleph\Runtime\Mutation\Managed';
    public const MUTATION_BUFFER = 'Eleph\Runtime\Mutation\MutationBuffer';
    public const MUTATION_CONTEXT = 'Eleph\Runtime\Mutation\MutationContext';
    public const READ_PROCESSOR = 'Eleph\Runtime\Type\ReadProcessor';
    public const RECORD = 'Eleph\Runtime\Storage\Record';
    public const TRIGGER_EVENT = 'Eleph\Runtime\Trigger\TriggerEvent';
    public const TRIGGER_PHASE = 'Eleph\Runtime\Trigger\TriggerPhase';
    public const UNIT_OF_WORK = 'Eleph\Runtime\UnitOfWork\UnitOfWork';
    public const VALUE_DECODER = 'Eleph\Runtime\Query\ValueDecoder';
    public const VERIFICATION = 'Eleph\Runtime\Verification\Verification';
    public const WRITE_PROCESSOR = 'Eleph\Runtime\Type\WriteProcessor';
}
