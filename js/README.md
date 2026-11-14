# portable-schema (JS)

JS implementation of [Portable Schema](../README.md), on [Hyperjump JSON Schema](https://github.com/hyperjump-io/json-schema). Node 22.3+; it also runs bundled and in a browser.

Written in TypeScript, published as plain ES modules with type declarations:

- **Source:** `src/*.ts`, compiled with `tsc` in strict mode to `dist/` (ESM + `.d.ts`). No bundler, no CommonJS build.
- **Package:** `"type": "module"`, `exports` pointing to `dist/`, Node 22.3+.
- **Tests:** Vitest, running the shared fixtures.

The validator carries what it needs from the repository, so it runs from the package's `dist` alone:

- **Embedded resources:** the meta-schemas of `spec/v1/`, the type library of `types/v1/` and `messages/en.json` are embedded as JSON text in `src/embedded.ts`. [`scripts/embed.mjs`](scripts/embed.mjs) generates that file before every build and test run; it is not committed.
- **No file access, no Node.js imports:** the validator reads no files and its sources import no Node.js module, so it also runs bundled (e.g. Vite, Nitro) and in a browser.
- **`Registry.addDirectory()` is Node.js only:** it reads files, with modules it gets from `process.getBuiltinModule()` when called (Node 22.3+). Elsewhere it throws; add the schemas with `Registry.add()`.

## Usage

```ts
import { Formatter, Registry, Validator } from "portable-schema";

const registry = Registry.withTypes().addDirectory("schemas", "drupal"); // schemas/block.block.json → drupal:block.block
const validator = (await Validator.create(registry))
  .withCheck("drupal:UniqueField", (requests) => /* one CheckResult per request */);

const result = validator.validate(data, "drupal:block.block", { partial: true });
result.valid;
result.errors;     // ValidationError[]: instanceLocation, keywordLocation, code, params
result.unchecked;  // Unchecked[]
Formatter.create().messages(result); // English messages

validator.subschemaAt(data, "drupal:block.block", "settings.label"); // { schemaLocations, unresolved }
```

- **`Validator.create()` compiles** every schema of the registry, including the targets of `portable-typeBy`, since Hyperjump compiles asynchronously. It rejects a schema outside the profile, and keeps a copy of the registry, so schemas added later are not seen. `validate()` is synchronous.
- **Data** is normalized to the JSON data model ([`spec/normalization.md`](../spec/normalization.md)): `undefined` properties are absent, and values `JSON.parse` cannot produce are unsupported: a `type` error.
- **`subschemaAt()`** returns the schemas that apply at a location of the data, as `keywordLocation`s, and the keywords left `unresolved` by a missing value ([`spec/validation.md`](../spec/validation.md)).
- **Exports:** `Exporter.inline()` (self-contained JSON Schema 2020-12) and `Exporter.draft04()`.

## Design

The same as the PHP implementation, with the same names ([validator design](../docs/architecture/validator.md)). Hyperjump's evaluation plugins record the evaluation (`hyperjump.ts`); the shaper, formats, type selection, checks and messages are ports of the PHP classes.

One difference: PHP reads the type library and the message catalog from the repository, at `Registry::TYPES_DIRECTORY` and `Catalog::DEFAULT`. JS embeds them and has neither constant.

## Tests

```sh
npm run build
npm test
```

`npm test` type-checks the tests and runs every fixture under [`fixtures/`](../fixtures) with Vitest. Both scripts first generate `src/embedded.ts`; to run `tsc`, `tsx` or `vitest` directly after changing `spec/`, `types/` or `messages/`, run `node scripts/embed.mjs` in `js/` first.
