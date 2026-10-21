# portable-schema (JS)

JS implementation of [Portable Schema](../README.md), on [Hyperjump JSON Schema](https://github.com/hyperjump-io/json-schema). Node 22+.

Written in TypeScript, published as plain ES modules with type declarations:

- **Source:** `src/*.ts`, compiled with `tsc` in strict mode to `dist/` (ESM + `.d.ts`). No bundler, no CommonJS build.
- **Package:** `"type": "module"`, `exports` pointing to `dist/`, Node 22+.
- **Tests:** Vitest, running the shared fixtures.

## Usage

```ts
import { Formatter, Registry, Validator } from "portable-schema";

const registry = Registry.withTypes().addDirectory("schemas");
const validator = (await Validator.create(registry))
  .withCheck("drupal:UniqueField", (requests) => /* one CheckResult per request */);

const result = validator.validate(data, "drupal:block.block", { partial: true });
result.valid;
result.errors;     // ValidationError[]: instanceLocation, keywordLocation, code, params
result.unchecked;  // Unchecked[]
Formatter.create().messages(result); // English messages
```

- **`Validator.create()` compiles** every schema of the registry, including the targets of `portable-typeBy`, since Hyperjump compiles asynchronously. It rejects a schema outside the profile, and keeps a copy of the registry, so schemas added later are not seen. `validate()` is synchronous.
- **Exports:** `Exporter.inline()` (self-contained JSON Schema 2020-12) and `Exporter.draft04()`.

## Design

The same as the PHP implementation, with the same names ([validator design](../docs/architecture/validator.md)). Hyperjump's evaluation plugins record the evaluation (`hyperjump.ts`); the shaper, formats, type selection, checks and messages are ports of the PHP classes.

## Tests

```sh
npm run build
npm test
```

`npm test` type-checks the tests and runs every fixture under [`fixtures/`](../fixtures) with Vitest.
