# Drupal compatibility corpora

Sample values with Drupal's, Portable Schema's and the standard's verdicts, for [`docs/drupal-compatibility.md`](../../../docs/drupal-compatibility.md). For planning only; not conformance fixtures.

| File | Covers |
|---|---|
| [`email.json`](email.json) | `email` |
| [`uri.json`](uri.json) | `uri`: the `uri` DataType, link fields, `UrlHelper::isValid()` |
| [`pattern.json`](pattern.json) | Core `Regex` constraints in PCRE and ECMA-262 |
| [`string-length.json`](string-length.json) | `Length` and `maxLength` |
| [`empty-and-required.json`](empty-and-required.json) | `NotBlank`, `Choice`, empty strings, required keys and `null` |
| [`numbers.json`](numbers.json) | `integer`, `decimal`, `float` |
| [`core-types.json`](core-types.json) | `uuid`, `color_hex`, `machine_name`, `langcode`, `label`, `text`, `weight` |

Each file has `environment` (where the verdicts were produced), `columns` (what each verdict column ran) and `cases`.
