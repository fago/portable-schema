# Normalization

Status: draft.

Before validation, the input is turned into the JSON data model. Validation runs on that data, and nothing is converted on the way: a value of the wrong type is a validation error.

## The JSON data model

Validation always runs on JSON values: object, array, string, number, boolean, null. How the input gets there:

| Input | Rule |
|---|---|
| JSON | As parsed. Duplicate keys are an error (I-JSON) |
| YAML | Parsed with the YAML 1.2 core schema; dates stay strings; tags are resolved before validation or refused; anchors and aliases are resolved; merge keys (`<<`) are refused |
| PHP arrays | The schema decides: where it expects an object, an array is a map, `[]` included; where it expects an array, an array with keys `0…n-1` in order is a list. Any other array where a list is expected is an error. `\stdClass` is always an object |
| JS values | As `JSON.parse` would produce them. `undefined` is absent |

## Absent, null and empty

- **Absent and `null` are different.** A key that is missing is absent; a key whose value is `null` is present.
- **`required` means present.** Whether `null` is allowed is up to `type`: with a type that excludes `null`, a required key must be present and not `null`.
- **`[]`, `{}` and `""` are values.** A required list, map or string accepts them. "Must not be empty" is a separate constraint: `minItems`, `minProperties`, `minLength` (Drupal's `NotBlank`, `Count`).
- **Output is validated as it is.** Absent and `null` stay different facts for a JSON client, so normalization never converts between them on output.

## Out of scope

- **Casting values sent in the wrong form** (`"3"` for an integer, `""` for "nothing entered", a single value for a list) is the host's job before validation: Drupal's Typed Data casts on set, form libraries have their own coercion. The validator expects typed JSON.
- **Merging with stored values** (absent keeps what is stored, `null` clears it) belongs to the layer that saves data, e.g. Data Surfaces.
- **How a host converts its own representations** (entities, handles, value objects) into JSON values is the host's concern.
