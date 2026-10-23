# Examples

Showcases of real systems, run by both runners. Each showcase is self-contained: every `$ref` leads to a file in its own `schemas/`.

## Layout

```
drupal/config/
  schemas/                                     mounted as drupal: one file per schema ID
    block.block.json                             drupal:block.block
    block.settings.%2A.json                      drupal:block.settings.*
    catalog/plugins/block.json                   drupal:catalog/plugins/block
  block.block.olivero_site_branding.yml        one instance, in its native format
  block.block.olivero_site_branding.test.json  what validating it gives
```

- **`schemas/`** holds the showcase's schemas and catalogs. The runner mounts it as `drupal` ([`spec/registry.md`](../../spec/registry.md)) next to the type library, and mounts nothing else. Schemas are not shared between showcases.
- **One data file per validated instance,** directly in the showcase directory, in its native format: Drupal config as YAML named like the config object, entities, component props and tool arguments as JSON. Variants of an instance get a `--<case>` suffix, e.g. `node.article--missing-title.json`.
- **`<name>.test.json`** next to each data file holds only the expectations, defined by [`../expectation.schema.json`](../expectation.schema.json).

## Expectation file: `<name>.test.json`

| Key | Required | Meaning |
|---|---|---|
| `schema` | yes | The ID of the schema the data is validated against |
| `valid` | yes | The expected verdict |
| `errors` | no | **Exactly** the expected errors, in shaped order, compared on `instanceLocation`, `code` and `params` |
| `unchecked` | no | Exactly the expected unchecked entries |
| `messages` | no | The English message of each error, in order, compared as text |
| `options` | no | Validation options: `partial`, `emptyAsMissing`, `locations` |
| `checks` | no | Local callbacks as data, as in the [conformance fixtures](../conformance/README.md#test-file-nametestjson) |

```json
{
  "schema": "drupal:node.article",
  "valid": false,
  "errors": [ { "instanceLocation": "", "code": "required", "params": { "property": "title" } } ],
  "messages": [ "Title is required." ]
}
```

The runners read YAML into the JSON data model: symfony/yaml in PHP, `yaml` in JS.
