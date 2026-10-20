# Validation

Status: draft.

How a validator evaluates data against a schema, and the options that change what it reports. Results follow [`errors.md`](errors.md).

## Steps

1. **Normalize** the data to the JSON data model (see `normalization.md`, planned).
2. **Evaluate** every keyword of the schema, resolving references through the registry and running named checks (see [`vocabulary.md`](vocabulary.md)). Annotations are collected along the way.
3. **Shape** the raw errors to one per problem (see [`errors.md`](errors.md)).

## Options

Options only filter what is reported. The schema is always evaluated in full, since keywords like `portable-typeBy`, `portable-compare` and `if` depend on other values.

| Option | Default | Effect |
|---|---|---|
| `partial` | `false` | Missing values are not errors. Values that are present are validated fully |
| `emptyAsMissing` | `false` | With `partial`, empty strings, empty arrays and empty objects count as missing |
| `locations` | all | Only errors and unchecked entries at or below these JSON Pointers are reported |

### `partial`

For saving incomplete data: drafts, PATCH requests, multi-step forms. With `partial: true`:

- `required` and `dependentRequired` report no errors for missing properties.
- `portable-typeBy` reports no `typeBy.unresolved` error when a `source` value is missing.
- `portable-compare` reports no error when the compared value is missing.
- Every other keyword applies as usual to the values that are present.

### `emptyAsMissing`

With `partial: true` and `emptyAsMissing: true`, a property whose value is `""`, `[]` or `{}` is treated as missing:

- it causes no errors, not even `unknownProperty`;
- `minLength`, `minItems` and `minProperties` don't apply to it;
- it isn't removed from the data.

Without `partial`, the option has no effect.

### `locations`

For validating part of the data, e.g. one form field on blur or one step of a wizard. Only results whose `instanceLocation` equals one of the given pointers, or lies below one, are reported. An error that names a property in `params.property` (`required`, `unknownProperty`, `propertyNames`, `dependentRequired`) counts at that property's location, so `["/a/c"]` reports `c` missing from `/a`.

Locations combine with `partial`, e.g. to validate the fields of one wizard step while later steps are still empty.
