# Errors

Status: draft.

Validation returns data, not text. Messages are produced from this data by a formatter (see [`docs/architecture/messages.md`](../docs/architecture/messages.md)); message text is not part of the spec.

## Result

```json
{
  "valid": false,
  "errors": [
    {
      "instanceLocation": "/plugin",
      "keywordLocation": "/properties/plugin/$ref/oneOf",
      "absoluteKeywordLocation": "drupal:catalog/plugins/block#/oneOf",
      "code": "enum",
      "params": { "value": "foo_block", "allowed": ["system_branding_block", "system_main_block"] }
    }
  ],
  "unchecked": [
    { "instanceLocation": "/id", "code": "check.UniqueField", "reason": "noProvider" }
  ]
}
```

- **`valid`** is `false` if `errors` is not empty. Entries in `unchecked` don't make a result invalid.
- **Locations** are JSON Pointers (RFC 6901). `keywordLocation` follows `$ref`s, as in the JSON Schema output formats.
- **`code`** is a stable identifier (table below). **`params`** are plain JSON values; their keys are fixed per code.
- **`unchecked`** lists checks that could not run, with `reason`: `noProvider` (no provider registered) or `offline` (static validation without the needed catalog).
- The shape follows the "list" output format of the next JSON Schema release, extended with `code`, `params` and `unchecked`.

## Codes

| Code | From | `params` |
|---|---|---|
| `type` | `type` | `expected` (array of type names), `actual` |
| `required` | `required` | `property` |
| `unknownProperty` | `additionalProperties`, `unevaluatedProperties` | `property` |
| `const` | `const` | `expected` |
| `enum` | `enum`, `oneOf` of `const`s | `value`, `allowed` (array) |
| `minLength`, `maxLength` | same | `limit`, `actual` |
| `pattern` | `pattern` | `pattern` |
| `format` | `format` | `format` |
| `minimum`, `maximum`, `exclusiveMinimum`, `exclusiveMaximum` | same | `limit` |
| `multipleOf` | same | `divisor` |
| `minItems`, `maxItems` | same | `limit`, `actual` |
| `uniqueItems` | same | `indexes` (array of the duplicates' indexes) |
| `minProperties`, `maxProperties` | same | `limit`, `actual` |
| `propertyNames` | same | `property` |
| `dependentRequired` | same | `property`, `dependency` |
| `not` | `not` | none |
| `oneOf`, `anyOf` | same, when no branch can be singled out | `matched` (number of matching branches) |
| `typeBy.unresolved` | `portable-typeBy` | `value`, `resolved` (the URI tried) |
| `compare.<op>` | `portable-compare`; `<op>` is `lt`, `le`, `eq`, `ne`, `ge`, `gt` | `other` (the compared value), `otherLocation` |
| `check.<Name>` | `portable-check` | the check's options, plus check-specific values |

Errors from a schema returned by `portable-refine` carry the code of the failing keyword.

## Shaping

Raw JSON Schema evaluation reports many errors for one problem. Implementations reduce them so that each problem is reported once:

1. **`enum`-like `oneOf`:** when every branch is a `const`, report one `enum` error with all allowed values. This includes catalogs.
2. **`oneOf` / `anyOf` with object branches:** report only the errors of the best branch:
   - the branch whose `const` discriminator matches the instance;
   - otherwise the branch with the fewest errors;
   - ties go to the first branch.
   Report `oneOf` / `anyOf` itself only if no branch can be singled out.
3. **`portable-typeBy`:** report only errors of the resolved schema, or `typeBy.unresolved` if neither the target nor the fallback exists.
4. **`if` / `then` / `else`:** report the errors of the applied branch, not of `if`.
5. **`$ref`:** errors keep the location of the referencing keyword.
6. **Order:** by `instanceLocation`, then by `keywordLocation`.

Annotations such as `title` are not part of errors. Formatters take them from the annotations collected during validation.
