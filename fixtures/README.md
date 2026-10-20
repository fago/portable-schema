# Fixtures

Language-neutral JSON test data, shared by the PHP and JS implementations.

| Path | Purpose |
|---|---|
| [`conformance/`](conformance) | Normative cases. Both implementations must pass every one |
| [`exports/`](exports) | Schemas with their expected `inline()` export; the validator and a plain 2020-12 validator on the export must agree on the cases |
| [`examples/`](examples) | Worked examples for planning and docs, e.g. schemas an integration would generate. Not normative |

Runners find every `*.test.json` and run it against the `.json` file of the same name (see [`conformance/`](conformance)). For examples, every file in a `schemas/` or `catalogs/` directory next to the fixture, or above it below `examples/`, joins the registry.
