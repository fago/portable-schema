# Conformance fixtures

Cases both implementations must pass. Together with the spec, they define correct behaviour.

Conformance also includes the official [JSON-Schema-Test-Suite](https://github.com/json-schema-org/JSON-Schema-Test-Suite) for 2020-12, filtered to the profile: tests for keywords outside the profile are skipped, and format tests follow the pinned checks ([decision 0005](../../docs/decisions/0005-profile-only-validators.md)).

## Layout

Each fixture is a pair of files side by side, like a source file and its test:

```
validation/portable-typeBy/block-settings.json        the schema under test
validation/portable-typeBy/block-settings.test.json   its test cases
```

| Path | Covers |
|---|---|
| `validation/<area>/` | Keywords, types, formats, shaping, catalogs, options and the added keywords |
| `messages/` | English messages from the standalone formatter and [`messages/en.json`](../../messages/en.json) |
| `normalization/` (planned) | Object vs list, coercion |

Runners find every `*.test.json` and run it against the `.json` file of the same name.

## Schema file: `<name>.json`

Defined by [`../schema.schema.json`](../schema.schema.json).

```json
{
  "description": "portable-typeBy selects the schema by a sibling value",
  "registry": { "test:settings": { "type": "object" } },
  "schema": { "$schema": "https://portable-schema.dev/v1/schema", "type": "object" }
}
```

- **`schema`:** the schema under test.
- **`registry`:** extra schemas and catalogs by `$id`. The `portable:*` types always come from [`types/v1/`](../../types/v1).

## Test file: `<name>.test.json`

Defined by [`../tests.schema.json`](../tests.schema.json). An array of test cases:

| Key | Required | Meaning |
|---|---|---|
| `description` | yes | What the case shows |
| `data` | yes | The instance |
| `valid` | yes | The expected verdict |
| `errors` | no | **Exactly** the expected errors, in shaped order, compared on `instanceLocation`, `code` and `params` |
| `unchecked` | no | Exactly the expected unchecked entries |
| `messages` | no | The English message of each error, in order, compared as text |
| `options` | no | Validation options: `partial`, `emptyAsMissing`, `locations` |
| `checks` | no | Local callbacks as data: the canned result per check name, which runners turn into a callback |

```json
[
  {
    "description": "local callback reports a duplicate",
    "checks": { "test:Unique": { "errors": [ { "code": "check.test:Unique", "params": { "value": "Hello" } } ] } },
    "data": { "title": "Hello" },
    "valid": false,
    "errors": [ { "instanceLocation": "/title", "code": "check.test:Unique", "params": { "value": "Hello" } } ]
  }
]
```
