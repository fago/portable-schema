# Fixtures

Language-neutral JSON test data, shared by the PHP and JS implementations.

| Path | Purpose |
|---|---|
| [`conformance/`](conformance) | Normative cases. Both implementations must pass every one |
| [`exports/`](exports) | Schemas (`schema`, plus `registry`) with their expected `inline()` (`export`) or `draft04()` (`draft04`, `null` = refused) export; the validator and a plain validator on the export must agree on the `tests`. Schema: [`exports.schema.json`](exports.schema.json) |
| [`loading.json`](loading.json) | Schemas the registry or the validator refuses (`refused`), or accepts. Schema: [`loading.schema.json`](loading.schema.json) |
| [`examples/`](examples) | Worked examples for planning and docs, e.g. schemas an integration would generate. Not normative. Expectations: [`expectation.schema.json`](expectation.schema.json) |

Runners find every `*.test.json`. Below [`conformance/`](conformance), it holds test cases for the `.json` schema file of the same name. Below [`examples/`](examples), it holds the expectations for the `.yml` or `.json` data file of the same name, validated with the showcase's `schemas/` mounted.
