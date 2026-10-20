# Fixtures

Language-neutral JSON test data, shared by the PHP and JS implementations.

| Path | Purpose |
|---|---|
| [`conformance/`](conformance) | Normative cases. Both implementations must pass every one |
| [`exports/`](exports) | Schemas with their expected `inline()` (`export`) or `draft04()` (`draft04`, `null` = refused) export; the validator and a plain validator on the export must agree on the cases |
| [`loading.json`](loading.json) | Schemas the validators refuse when loading them (`refused`), or accept |
| [`examples/`](examples) | Worked examples for planning and docs, e.g. schemas an integration would generate. Not normative |

Runners find every `*.test.json` and run it against the `.json` file of the same name (see [`conformance/`](conformance)). For examples, every file in a `schemas/` or `catalogs/` directory next to the fixture, or above it below `examples/`, joins the registry.
