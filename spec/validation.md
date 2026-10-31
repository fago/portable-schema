# Validation

How a validator evaluates data against a schema, and the options that change what it reports. Results follow [`errors.md`](errors.md).

## Steps

1. **Normalize** the data to the JSON data model (see [`normalization.md`](normalization.md)). PHP arrays are decided during evaluation, where `type` is checked.
2. **Evaluate** every keyword of the schema, resolving references through the registry and running named checks (see [`vocabulary.md`](vocabulary.md)). Annotations are collected along the way.
3. **Shape** the raw errors to one per problem (see [`errors.md`](errors.md)).

## Registry

Schemas are found by id in a registry. How files get their ids is in [`registry.md`](registry.md).

- **Ids** are absolute URIs without a fragment, dot segments (`.`, `..`) or the `file:` scheme, and are not the id of a JSON Schema or Portable Schema meta-schema. Two ids must differ after percent-decoding, with the scheme and authority in lower case.
- **A schema's own `$id`**, if it has one, equals its id in the registry.
- **References** resolve through the registry only. The JSON Schema meta-schemas are not part of it.
- **Loading** refuses every schema outside the [profile](profile.md), including subschemas no data reaches.

## Options

Options only filter what is reported. The schema is always evaluated in full, since keywords like `portable-typeBy`, `portable-compare` and `if` depend on other values.

| Option | Default | Effect |
|---|---|---|
| `partial` | `false` | Missing values are not errors. Values that are present are validated fully |
| `emptyAsMissing` | `false` | With `partial`, empty strings, empty arrays and empty objects count as missing |
| `locations` | all | Only errors and unchecked entries at or below these JSON Pointers are reported; an error naming a property counts at that property |

### `partial`

For saving incomplete data: drafts, PATCH requests, multi-step forms. With `partial: true`:

- `required` and `dependentRequired` report no errors for missing properties.
- `portable-typeBy` reports no `typeBy.unresolved` error when a `source` value is missing.
- `portable-compare` reports no error when the compared value is missing.
- Every other keyword applies as usual to the values that are present.

### `emptyAsMissing`

With `partial: true` and `emptyAsMissing: true`, a property whose value is `""`, `[]` or `{}` is treated as missing:

- it causes no errors, not even `unknownProperty`;
- as a `portable-typeBy` source, it selects no schema;
- `minLength`, `minItems` and `minProperties` don't apply to it;
- it isn't removed from the data.

Without `partial`, the option has no effect.

### `locations`

For validating part of the data, e.g. one form field on blur or one step of a wizard. Only results whose `instanceLocation` equals one of the given pointers, or lies below one, are reported. An error that names a property in `params.property` (`required`, `unknownProperty`, `propertyNames`, `dependentRequired`) counts at that property's location, so `["/a/c"]` reports `c` missing from `/a`.

Locations combine with `partial`, e.g. to validate the fields of one wizard step while later steps are still empty.

## Subschema at a location

`subschemaAt(schema, instance, location)` returns the schemas that apply to the value at `location` of `instance`, in evaluation order. Widgets choose their control from them; hosts set a value by path and create missing containers from their `type`.

- **Result:** `schemaLocations`, the list of schema locations, and `unresolved`, the schema locations of keywords the walk skipped (see below).
- **Schema locations** are JSON Pointers into the root schema in the form of `keywordLocation` ([`errors.md`](errors.md)): they pass through `$ref` and `portable-typeBy` into the resolved schema, e.g. `/properties/settings/$ref/properties/label`.
- **A schema applies** together with the schemas its keywords apply, in this order:
  - `$ref`: the referenced schema, resolved through the registry;
  - `allOf`: every branch;
  - `anyOf`, `oneOf`: the branches the value is valid against; named checks don't run;
  - `if`: `then` or `else` by its outcome; `if` itself is not returned;
  - `portable-typeBy`: the resolved target or fallback.
  Each applied schema is expanded in place before the next keyword.
- **A labeled catalog** ([`profile.md`](profile.md)) is one schema; its branches are not returned.
- **Walk:** start with the root schema, expanded. For each reference token of `location`, replace the set by the child schemas of its members, each expanded:
  - object: `properties/<token>`; `additionalProperties` where the schema's own `properties` has no entry; `unevaluatedProperties` only when no `properties` entry in the whole set matches;
  - array: `prefixItems/<index>`, else `items`.
  The value decides between object and array; where it is missing, the schema's `type` does.
- **Missing values:** a keyword that needs a missing value to choose (`anyOf`, `oneOf`, `if`; `portable-typeBy` with a missing `source` value) is skipped and listed in `unresolved`. The walk continues with the schemas that resolve, so `schemaLocations` holds the schemas known to apply.
- **Validation** of a set value stays full evaluation with the `locations` option, since `portable-typeBy`, `portable-compare` and `if` depend on sibling values.
- **Location strings** are JSON Pointers (RFC 6901); `""` is the root.
  - **Dotted paths:** a string that does not start with `/` is split on `.`, each part one reference token, without escaping, so keys containing `.` or `/` need the pointer form. `/` means the root too.

Fixtures: [`fixtures/conformance/subschema/`](../fixtures/conformance/subschema).
