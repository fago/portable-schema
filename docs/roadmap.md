# Roadmap

Planned work that isn't started yet.

## Next

- **Linting.** A `portable-schema lint` command for schema files and site schema dumps, run in CI:
  - checks against the meta-schema (profile and vocabulary);
  - every `$ref` resolvable;
  - every `portable-typeBy` template complete;
  - pointers in the allowed Relative JSON Pointer subset;
  - patterns in the portable regex subset.

- **CLI with JSON output.** The same tool as the linter: `portable-schema validate <file> --schema <id> --dump <path> --format json` returns the result format plus English messages, with deterministic exit codes. Coding agents with a shell use it directly.

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
- **Agent support.**
  - An MCP server with `describe_schema`, `list_types`, `get_catalog` and `validate`: standalone from a site schema dump, and live through the Drupal integration.
  - A Claude Code skill and a hook that validates edited config files.
  - A generator for an `AGENTS.md` / `CLAUDE.md` snippet that tells agents how to validate in a project.
  - An `llms.txt` overview of the spec.
- **LLM export profiles.** Down-level exports of schemas as tool parameters and structured-output formats, per provider; e.g. OpenAI's strict mode requires `additionalProperties: false` and all properties in `required`.
- **"Did you mean" suggestions** in catalog and `enum` errors, for humans and agents.
