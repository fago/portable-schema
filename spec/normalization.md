# Normalization

Before validation, the input is turned into the JSON data model. Validation runs on that data, and nothing is converted on the way: a value of the wrong type is a validation error.

## The JSON data model

Validation always runs on JSON values: object, array, string, number, boolean, null. How the input gets there:

| Input | Rule |
|---|---|
| JSON | As parsed. Duplicate keys are an error (I-JSON) |
| YAML | Parsed with the YAML 1.2 core schema; dates stay strings; anchors and aliases are resolved; keys become strings. Refused: tags outside the core schema, merge keys (`<<`), `.inf` and `.nan`. Duplicate keys are an error |
| PHP arrays | Keys `0…n-1` in order make a list, any other array a map. `[]` is a list, except where the schema's `type` for that value allows `object` and not `array`: then it is a map. A list where `type` requires `object`, or a map where it requires `array`, is a `type` error with `actual` `array` or `object`, as for JSON input |
| PHP objects | As `json_encode` converts them: `\stdClass` and other objects by their public properties, `JsonSerializable` by `jsonSerialize()`, backed enums by their value. Unbacked enums, resources, closures, `NAN` and `INF` are a `type` error with `actual: "unsupported"`, reported by the schema that applies at the value |
| JS values | As `JSON.parse` would produce them. `undefined` is absent. Anything `JSON.parse` cannot produce (`Date`, `Map`, `NaN`, `Infinity`, `BigInt`, functions, symbols, class instances) is a `type` error with `actual: "unsupported"`, reported by the schema that applies at the value |

## Absent, null and empty

- **Absent and `null` are different.** A key that is missing is absent; a key whose value is `null` is present.
- **`required` means present.** Whether `null` is allowed is up to `type`: with a type that excludes `null`, a required key must be present and not `null`.
- **`[]`, `{}` and `""` are values.** A required list, map or string accepts them. "Must not be empty" is a separate constraint: `minItems`, `minProperties`, `minLength` (Drupal's `NotBlank`, `Count`).
- **Output is validated as it is.** Absent and `null` stay different facts for a JSON client, so normalization never converts between them on output.

## Out of scope

- **Casting values sent in the wrong form** (`"3"` for an integer, `""` for "nothing entered", a single value for a list) is the host's job before validation: Drupal's Typed Data casts on set, form libraries have their own coercion. The validator expects typed JSON.
- **Merging with stored values** (absent keeps what is stored, `null` clears it) belongs to the layer that saves data, e.g. Data Surfaces.
- **How a host converts its own representations** (entities, handles, value objects) into JSON values is the host's concern, e.g. Drupal's Typed Data `getValue()` or Symfony normalizers.
