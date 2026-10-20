# Roadmap

Planned work that isn't started yet.

## Next

- **Linting.** A `portable-schema lint` command for schema files and site schema dumps, run in CI:
  - checks against the meta-schema (profile and vocabulary);
  - every `$ref` resolvable;
  - every `portable-typeBy` template complete;
  - pointers in the allowed Relative JSON Pointer subset;
  - patterns in the portable regex subset.

## Later

- **PHPStan integration.** An extension with rules on constant arguments of the PHP model:
  - unknown types and catalogs, checked against the type library and an optional site schema dump;
  - pointer syntax;
  - `typeBy` template variables matching their `source` entries;
  - patterns in the portable regex subset.
  Later possibly a return-type extension that infers the data shape from a schema.
- **TypeScript integration.**
  - Type inference from schemas (`Infer<typeof schema>`).
  - Generated `.d.ts` files with literal union types for type, catalog and widget IDs, so `tsc` checks references and editors complete them.
