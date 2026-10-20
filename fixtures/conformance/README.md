# Conformance fixtures

Cases both implementations must pass. Together with the spec, they define correct behaviour.

Conformance also includes the official [JSON-Schema-Test-Suite](https://github.com/json-schema-org/JSON-Schema-Test-Suite) for 2020-12, filtered to the profile: tests for keywords outside the profile are skipped, and format tests follow the pinned checks ([decision 0005](../../docs/decisions/0005-profile-only-validators.md)).

## Layout

| Path | Covers |
|---|---|
| `validation/<keyword>/*.json` | One file per keyword or rule of the profile and vocabulary |
| `normalization/*.json` | Object vs list, coercion |
| `catalogs/*.json` | Catalog resolution, live and static |

## Format

The [JSON-Schema-Test-Suite](https://github.com/json-schema-org/JSON-Schema-Test-Suite) format, extended with expected errors and normalized data:

```json
[
  {
    "description": "typeBy selects the schema by a sibling value",
    "schema": { "$schema": "https://portable-schema.dev/v1/schema" },
    "registry": { "urn:example:a": {} },
    "tests": [
      {
        "description": "matching variant",
        "data": {},
        "valid": true
      },
      {
        "description": "unknown variant without fallback",
        "data": {},
        "valid": false,
        "errors": [
          { "instanceLocation": "/settings", "keyword": "portable-typeBy", "params": {} }
        ]
      }
    ]
  }
]
```

- **`registry`:** schemas and catalogs available for `$ref` resolution, keyed by `$id`.
- **`errors`:** expected errors, compared on `instanceLocation`, `keyword` and `params`. Message text is not compared.
- **`normalized`** (normalization cases): the expected data after normalization.
