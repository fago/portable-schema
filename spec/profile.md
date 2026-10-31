# Profile

This document covers formats, patterns and catalogs; the rest of the profile follows.

Schemas outside the profile are refused when they are loaded ([decision 0005](../docs/decisions/0005-profile-only-validators.md)).

## Keywords

The full list of allowed keywords follows. Annotations include `$comment`.

## Catalogs

A catalog restricts a value to the facts a system provides ([decision 0006](../docs/decisions/0006-catalogs-as-labeled-oneof.md)):

- **Labeled:** a `oneOf` whose branches each hold a distinct `const` and a `title`, optionally a `description` and `x-portable-*` annotations.
- **Unlabeled:** a plain `enum`.

`title` and `description` are translatable schema strings. A failed check reports one `enum` error with the allowed values.

## Formats

Formats come from the [JSON Schema format registry](https://github.com/json-schema-org/json-schema-spec/blob/main/specs/registries/format.json) and their checks follow the standard the registry names ([decision 0003](../docs/decisions/0003-standard-formats-not-stricter-than-drupal.md)).

- **`format` is an assertion.**
- **A schema using a format outside this list is refused.**
- **Each check below is pinned,** so PHP and JS give the same verdict. Fixtures under `fixtures/conformance/validation/formats/` define the edge cases.

| Format | Check | Based on |
|---|---|---|
| `date` | `YYYY-MM-DD`, a real calendar date (month and day ranges, leap years) | RFC 3339 full-date |
| `time` | `HH:MM:SS`, optional fraction, offset (`Z` or `±HH:MM`) required. No leap seconds | RFC 3339 full-time |
| `date-time` | `date`, `T` or `t`, `time` | RFC 3339 date-time |
| `duration` | ISO 8601 duration as in RFC 3339 appendix A, e.g. `P1Y2M`, `PT30M`, `P2W` | RFC 3339 |
| `email` | RFC 5321 `Mailbox`, ASCII only: a `Dot-string` or `Quoted-string` local part of at most 64 characters, `@`, and a domain as in `hostname` or an address literal: `[a.b.c.d]` with 1–3 digits per part, 0–255, leading zeros allowed (`Snum`); or `[IPv6:` an RFC 5321 `IPv6-addr` `]`, the tag case-insensitive. At most 254 characters in total. No comments, folding whitespace or general address literals | RFC 5321 §4.1.2, §4.5.3.1 |
| `hostname` | Dot-separated labels of letters, digits and hyphens, 1–63 characters each, not starting or ending with a hyphen, at most 253 in total; ASCII only | RFC 1123 |
| `ipv4` | Dotted quad, 0–255 per part, no leading zeros | RFC 2673 |
| `ipv6` | RFC 4291 text form, incl. `::` compression and an embedded IPv4 tail; no zone ID | RFC 4291 |
| `uri` | An absolute URI per the RFC 3986 grammar: scheme required | RFC 3986 |
| `uri-reference` | A URI or relative reference per the RFC 3986 grammar | RFC 3986 |
| `iri` | An absolute IRI per the RFC 3987 grammar: scheme required; `ucschar` and `iprivate` code points where the grammar allows them; the host is checked as syntax only, without IDNA processing | RFC 3987 |
| `iri-reference` | An IRI or relative reference per the RFC 3987 grammar; `""` is a valid reference | RFC 3987 |
| `uri-template` | RFC 6570 syntax (levels 1–4) | RFC 6570 |
| `uuid` | 8-4-4-4-12 hex digits, case-insensitive; any version | RFC 9562 |
| `json-pointer` | RFC 6901 syntax | RFC 6901 |
| `relative-json-pointer` | The core form of [decision 0002](../docs/decisions/0002-relative-json-pointer.md): no index adjustments | Relative JSON Pointer draft |
| `regex` | Within the portable regex subset (see [Patterns](#patterns)) | ECMA-262 |
| `decimal` | A string `[+-]?([0-9]+(\.[0-9]*)?\|\.[0-9]+)`, e.g. `-1.5`, `+1`, `1.`, `.5`, `007`; no exponent. Any number is valid too | Registry (names no grammar); the grammar is Drupal's, see [`docs/drupal.md`](../docs/drupal.md#data-types) |
| `int32` | An integer from −2^31 to 2^31 − 1 | Registry |
| `html` | Not checked: any string. The format names the content for hosts and widgets | Registry |
| `commonmark` | Not checked: any string | Registry |

**Not allowed in v1:**
- `idn-email`, `idn-hostname`: IDNA processing would differ between PHP and JS.
- `int64`, `uint8`, `int8`, `int16`, `double-int`, `float`, `double`, `decimal128`: number ranges beyond what JS represents exactly, or not needed yet.
- `byte`, `binary`, `base64url`, `char`, `http-date`, `media-range`, `sf-*`: not needed yet.

Adding a format later means pinning its check here and adding fixtures.

## Patterns

`pattern` and the `regex` format use ECMA-262 regular expressions (JSON Schema Validation §6.3.3).

- **Semantics:** as a JS `RegExp` with the `u` flag: code points, `\p{…}` property escapes, `$` only at the very end. Not anchored unless the pattern uses `^` and `$`.
- **PHP implementations** match with PCRE and the `u` and `D` modifiers, after translating syntax where PCRE differs, so PHP and JS agree.
- **Converting PCRE patterns** keeps PCRE's verdicts where it can (Drupal: [`docs/drupal.md`](../docs/drupal.md#regex-patterns)):
  - a final `$` without `D` becomes `\n?$`, since PCRE's `$` also matches before a final newline;
  - `\pX` and `\PX` without braces are invalid with `u`. `[^\PC]` becomes the explicit categories `[\p{Cc}\p{Cf}\p{Co}]`, without the unassigned `Cn` and the surrogates `Cs`, which UTF-8 input can't contain.
- **Unicode versions differ** between engines, e.g. PCRE2 10.42 uses Unicode 14, V8 13.6 Unicode 17. Code points that one of them doesn't know (`Cn`, also part of `\p{C}`) can get different verdicts: a known difference. Checks that depend on them run on the server.
- **Allowed syntax:** the portable subset is still to be defined.

## Errors

A failed format check reports the code `format.<name>`, e.g. `format.email` (see [`errors.md`](errors.md)).
