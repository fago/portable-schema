# portable-schema (JS)

JS implementation of [Portable Schema](../README.md). Not started yet.

Written in TypeScript, published as plain ES modules with type declarations:

- **Source:** `src/*.ts`, compiled with `tsc` in strict mode to `dist/` (ESM + `.d.ts`). No bundler, no CommonJS build.
- **Package:** `"type": "module"`, `exports` pointing to `dist/`, Node 22+.
- **Tests:** Vitest, running the shared fixtures.
