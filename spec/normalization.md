# Normalization

Status: draft.

Before validation, the input is turned into the JSON data model. With the `coerce` option, a published cast table also converts values that callers commonly send in the wrong form. Validation then runs on the normalized data, and implementations return it, so callers store what was validated.

## The JSON data model

Validation always runs on JSON values: object, array, string, number, boolean, null. How the input gets there:

| Input | Rule |
|---|---|
| JSON | As parsed. Duplicate keys are an error (I-JSON) |
| YAML | Parsed with the YAML 1.2 core schema; dates stay strings; tags are resolved before validation or refused; anchors and aliases are resolved; merge keys (`<<`) are refused |
| PHP arrays | The schema decides: where it expects an object, an array is a map, `[]` included; where it expects an array, an array with keys `0…n-1` in order is a list. Any other array where a list is expected is an error. `\stdClass` is always an object |
| JS values | As `JSON.parse` would produce them. `undefined` is absent |

Without `coerce`, nothing else changes: a value of the wrong type is a validation error.

## Absent, null and empty

These rules hold with and without `coerce`.

- **Absent and `null` are different.** A key that is missing is absent; a key whose value is `null` is present.
- **`required` means present.** Whether `null` is allowed is up to `type`: with a type that excludes `null`, a required key must be present and not `null`.
- **`[]`, `{}` and `""` are values.** A required list, map or string accepts them. "Must not be empty" is a separate constraint: `minItems`, `minProperties`, `minLength` (Drupal's `NotBlank`, `Count`).
- **Output is validated as it is.** Absent and `null` stay different facts for a JSON client, so normalization never converts between them on output.

## Coercion (`coerce`)

For input from forms, query strings, agents and PATCH requests. Off by default; enabled with the validation option `coerce: true`. The table applies only where the schema's `type` doesn't already accept the value as it is.

| Input value | Schema expects | Result |
|---|---|---|
| `null` | an optional key whose `type` excludes `null` | Absent: the caller means "the default" |
| `""` | a non-string `type` | Absent: forms post `""` for "nothing entered" |
| A string matching `^-?(0\|[1-9][0-9]*)$` | `integer` | That integer, if within the I-JSON range |
| A string in JSON number syntax | `number` | That number |
| `"true"` / `"false"`, `"1"` / `"0"` | `boolean` | `true` / `false` |
| A single value that isn't an array | `array` | Wrapped in a one-element list |
| `[]` | `array` | Left alone |
| `{}` | `object` | Left alone. "Empty means nothing to do" is the caller's decision, not normalization's |

- **Nothing else is coerced.** Numbers are not converted to strings, booleans not to numbers, objects not to lists. Such values stay a validation error.
- **Coercion follows the schema's `type`.** Where a schema allows several types, a value that one of them accepts as it is is left alone.
- **`portable-typeBy` targets are coerced like any schema,** once the target is resolved.

## Results

- **Implementations return the normalized data** next to the result, so a caller can store it.
- **Fixtures for normalization** (`fixtures/conformance/normalization/`) give the expected data after normalization in `normalized`, compared exactly.

## Out of scope

- **Merging with stored values** (absent keeps what is stored, `null` clears it) belongs to the layer that saves data, e.g. Data Surfaces.
- **How a host converts its own representations** (entities, handles, value objects) into JSON values is the host's concern.
