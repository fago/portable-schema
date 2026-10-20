# Vocabulary

Status: draft. This document starts with `portable-check`; the other keywords follow.

The syntax of every keyword is defined in [`v1/vocab.json`](v1/vocab.json). This document defines their semantics.

## `portable-check`

Named checks: validation logic that is implemented in code, not expressible with keywords. Examples: a value must be unique among stored data, or a reference must point to an existing entity.

```json
"title": {
  "$ref": "portable:label",
  "portable-check": { "drupal:UniqueField": {} }
},
"author": {
  "$ref": "portable:integer",
  "portable-check": { "drupal:ValidReference": {}, "drupal:ReferenceAccess": {} }
}
```

### Syntax

- **A map** from check names to their options.
- **Names are namespaced** by the integration that provides the check: `<namespace>:<name>`, e.g. `drupal:UniqueField`.
- **Options** are a JSON object, validated against the check's manifest.

### Evaluation

For each name, the validator asks for the check's result in this order:

1. **A local callback** registered for the name on the validator, if any. It overrides any provider.
2. **A provider** registered for the name.
3. **Otherwise** the check is reported in `unchecked` with reason `noProvider`.

Results:

- **Errors use the code `check.<name>`** (e.g. `check.drupal:UniqueField`), or sub-codes `check.<name>.<sub>` declared in the manifest.
- **An error may carry a rendered `message`.** Formatters use it instead of the catalog.
- **A check that could not run** is reported in `unchecked` with a reason (see [`errors.md`](errors.md)). It never counts as passed.

### Check manifests

Each check is described by a manifest, shipped by its integration, e.g. in a site schema dump:

```json
{
  "id": "drupal:UniqueField",
  "options": { "type": "object", "additionalProperties": false },
  "runs": "server",
  "messages": { "check.drupal:UniqueField": { "text": "{label} “{value}” is already taken." } }
}
```

| Key | Meaning |
|---|---|
| `id` | The namespaced name |
| `options` | A Portable Schema for the options. Linting checks schemas against it |
| `runs` | `server`: implemented only where the system's data lives. `both`: implemented in PHP and JS, and then covered by fixtures like keywords |
| `messages` | Source texts for the check's codes, in the [catalog format](../docs/decisions/0001-message-catalog-format.md) |

A local callback replaces a check's implementation, not its manifest: the name, options and codes stay the same.
