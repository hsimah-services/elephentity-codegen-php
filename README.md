# elephentity-codegen-php

The PHP builder for [Elephentity](https://github.com/hsimah/elephentity).

It reads one JSON request on stdin — the compiled spec, plus the target's configuration
— and writes one JSON response on stdout: a path and a body per file. It never touches
the filesystem. Signing and writing happen in
[elephentity-codegen](https://github.com/hsimah/elephentity-codegen), after this exits.

```bash
echo '{"elephentity":1,"irVersion":"1.0","target":"php","config":{},
       "outputDirectory":"out","schema":{}}' | ./bin/eleph-gen-php
```

That fails on the empty schema, which is the point: it should be obvious how.

## Installing it

```bash
composer require --dev elephentity/codegen-php
```

Then name it in `eleph.json`:

```json
{
  "targets": {
    "php": {
      "builder": "vendor/bin/eleph-gen-php",
      "output": "generated",
      "namespace": "App\\Entity",
      "typeNamespace": "App\\Type"
    }
  }
}
```

`namespace` and `typeNamespace` are read and validated here, not upstream. Nothing else
in the pipeline knows what they mean.

## It depends on nothing of Elephentity's

Not the compiler, not the runtime, not the orchestrator. The IR value objects in `src/Ir`
are a copy, and the runtime classes generated code refers to are strings in
`src/Runtime.php` rather than imports. That is deliberate: a builder that had to
`composer require` the framework would be a builder no other language could write. The
version gate is what holds the copy in step — a mismatch is a refusal, never a silent
misread.

## Working on it

There is no local PHP; everything runs in a container:

```bash
./tools/php composer ci          # style, static analysis, tests
./tools/php vendor/bin/phpunit --filter GoldenTest
```

PHPStan runs at **level max** with no baseline exclusions.

## The golden fixtures

`tests/fixtures/golden/*/` holds a committed request and the exact response it produces.
They are the specification of this program in the only form another implementation can
consume: when this is rewritten in Rust, the fixtures do not change and the new binary
has to reproduce them byte for byte.

When a deliberate change moves them, regenerate and read the diff — it is the clearest
description available of what the change did to every project's generated tree.
