# Drupal compatibility of formats and constraints

Status: for [issue #2](https://github.com/fago/portable-schema/issues/2). The proposed resolutions are adopted in `spec/`, `types/`, [`docs/drupal.md`](drupal.md#compatibility) and [decision 0007](decisions/0007-pinned-checks-follow-the-standard.md). The alternatives were not taken; the `iri` option stays [open](drupal.md#open-questions), and so does the `i` modifier, since `pattern` has no flags. The "Portable Schema" columns show the pins before the adoption.

**Principle:** Portable Schema must not be stricter than Drupal's validators, but it stays with the standard JSON Schema formats.

## Method

- **Drupal's verdicts** come from Drupal's own code, run on a booted site: Drupal core 11.x at `ad3428034a5b` (11.4-dev), minimal profile plus `link`, on SQLite.
  - Values go through Typed Data (`DataDefinition::create($type)`), typed config (`TypedConfigManager`), a link field on an entity, and the helpers the Form API elements call.
  - The `Regex`, `Length`, `NotBlank` and `Choice` cases run on a plain Symfony validator with the same constraint options; core's constraint classes only change messages or defaults that the cases set.
  - Runtime: PHP 8.3.6 with PCRE2 10.42 and ICU 74.2. From core's `composer.lock`: egulias/email-validator 4.0.4, symfony/validator 7.4.10.
- **Portable Schema verdicts** come from a script that reimplements the pinned checks of `spec/profile.md` and the types in `types/v1/`, since there is no implementation yet. It ran in Node.js 24.22 (V8 13.6, Unicode 17.0).
- **Standard verdicts** follow the definition the format registry names. The email, uri and uuid checks pass every string case of the JSON-Schema-Test-Suite (`tests/draft2020-12/optional/format/` at `89e83a7`).
- **Corpora with all verdicts** are in [`fixtures/examples/drupal-compatibility/`](../fixtures/examples/drupal-compatibility). Each file names what every column ran. The scripts that produced them are not part of this repository.
- **Code references** are paths in the Drupal core repository at that commit, or in the named vendor package.
- **(U)** marks claims that were not verified by running code.

## Summary

| Item | Finding | Proposed resolution |
|---|---|---|
| `email` | Drupal is RFC-based but stricter than RFC 5321: no quoted local parts, no address literals, no single-label domains. It also accepts UTF-8 addresses (RFC 6531). | Pin `email` to RFC 5321 Mailbox, as the registry defines it; this changes decision 0003. Drupal's exact check runs on the server as `drupal:Email`. UTF-8 addresses: a known difference while `idn-email` is out of v1 |
| `uri` | The `uri` DataType only requires `parse_url()` to find a scheme. Link fields add semantic checks. Core's `route:<nolink>` is not an RFC 3986 URI. | Keep `format: uri`. The integration percent-encodes `<` and `>` in `route:` URIs. Link checks run on the server. Other non-URIs Drupal accepts are a known difference. Option: allow `iri` in v1 for link fields |
| `pattern` | PCRE `$` accepts a trailing newline; `Regex` skips `""`; `[^\PC]` is not valid ECMA-262; Unicode tables differ between PCRE2 and V8 | Keep ECMA-262 semantics. The PHP implementation matches with `D` and `u`. The converter rewrites a final `$` to `\n?$` and `[^\PC]` to stable categories. Unassigned code points stay server-only |
| String length | Drupal counts code points, like `maxLength` | No change. Note for widgets: the HTML `maxlength` attribute counts UTF-16 units (U) |
| `NotBlank`, `Choice`, `required` | `NotBlank` also rejects `null`, `false`, `[]`. `Choice` skips `null`. `requiredKey` and `nullable` are only enforced for `FullyValidatable` types. `Email`, `Uuid` and `Regex` skip `""` | Map `NotBlank` per JSON type. Add `null` to the `enum` of nullable values. Emit `required` and non-null types only for `FullyValidatable` types. Allow `""` where Drupal's constraint skips it |
| Numbers | Drupal's integers reach ±2^63; its decimals accept `+1`, `1.`, `.5`, `007` and JSON numbers; numeric strings pass | Keep the I-JSON range as a known difference. Widen the pinned `decimal` grammar to Drupal's syntax. Strings are a normalization question. Core's `format: number` should become `format: decimal` |
| `uuid`, `color_hex`, `machine_name`, `langcode` | Drupal's `uuid` is stricter (version, variant, nil and max UUID) and accepts `""`. `color_hex` and `machine_name` agree except for the trailing newline and `""`, both accepted by Drupal only. `portable:langcode` rejects `x-default` and `site_default`, which Drupal accepts | Widen `portable:langcode` to RFC 5646 well-formed tags. Config `langcode` allows `site_default` next to `portable:langcode`. The trailing newline in `portable:color` and `portable:identifier` stays a known difference |

## 1. `email`

### Drupal

- **Typed Data and config:** the `email` DataType and the config schema type `email` carry the `Email` constraint (`core/config/schema/core.data_types.schema.yml`, `email:`).
- **Strict mode:** core's `EmailConstraint` forces `VALIDATION_MODE_STRICT` (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/EmailConstraint.php:35`). In strict mode, Symfony's `EmailValidator` calls `Egulias\EmailValidator\EmailValidator::isValid($value, new NoRFCWarningsValidation())` (symfony/validator `Constraints/EmailValidator.php:83-86`).
- **Forms:** the Form API `email` element uses the `email.validator` service, `Drupal\Component\Utility\EmailValidator`, with the same `NoRFCWarningsValidation` (`core/lib/Drupal/Component/Utility/EmailValidator.php:30`, `core/lib/Drupal/Core/Render/Element/Email.php:79`). The element trims the value first (`Email.php:76`), so it accepts leading and trailing spaces and newlines. It also renders `type="email"`, so browsers apply the WHATWG check before submitting (U).
- **`NoRFCWarningsValidation`:** RFC 5322 parsing that also fails on any RFC warning. That rejects:
  - quoted strings and address literals;
  - single-label domains, such as `joe@localhost`;
  - local parts over 64 characters.
  egulias accepts UTF-8 in the local part and in the domain (RFC 6531).
- **Empty string:** the constraint skips `""` (symfony/validator `Constraints/EmailValidator.php:54`). Core ships `mail: ''` in `system.site.yml`.
- **Email field:** `EmailItem` adds `Length` max 254 (`core/lib/Drupal/Core/Field/Plugin/Field/FieldType/EmailItem.php:62`).

### Corpus

| Value | Note | Drupal | Portable Schema | Standard |
|---|---|---|---|---|
| `joe@example.com` |  | valid | valid | valid |
| `joe.bloggs+tag@example.com` |  | valid | valid | valid |
| `o'reilly@example.com` |  | valid | valid | valid |
| `te~st@example.com` |  | valid | valid | valid |
| `joe@localhost` | single-label domain | **invalid** | valid | valid |
| `joe@io` |  | **invalid** | valid | valid |
| `joe@123.com` |  | valid | valid | valid |
| `joe@c--n.com` |  | valid | valid | valid |
| `joe@255.255.255.255` | IPv4-looking domain without brackets | valid | valid | valid |
| `"joe bloggs"@example.com` | quoted local part with space | **invalid** | **invalid** | valid |
| `"joe..bloggs"@example.com` | quoted local part with double dot | **invalid** | **invalid** | valid |
| `"joe@bloggs"@example.com` | quoted local part with @ | **invalid** | **invalid** | valid |
| `""@example.com` | empty quoted local part | **invalid** | **invalid** | valid |
| `"test"@example.com` | quoted local part, no special characters | **invalid** | **invalid** | valid |
| `joe@[127.0.0.1]` | IPv4 address literal | **invalid** | **invalid** | valid |
| `joe@[IPv6:::1]` | IPv6 address literal | **invalid** | **invalid** | valid |
| `joe@[127.0.0.300]` | invalid IPv4 address literal | **invalid** | **invalid** | **invalid** |
| `jöe@example.com` | non-ASCII local part (RFC 6531 only) | valid | **invalid** | **invalid** |
| `joe@exämple.com` | IDN domain, Unicode form (RFC 6531 only) | valid | **invalid** | **invalid** |
| `joe@xn--exmple-cua.com` | IDN domain, A-label form | valid | valid | valid |
| `joe@example.com.` | trailing dot in domain | **invalid** | **invalid** | **invalid** |
| `joe.@example.com` | dot at end of local part | **invalid** | valid | **invalid** |
| `.joe@example.com` | dot at start of local part | **invalid** | valid | **invalid** |
| `jo..e@example.com` | two dots in local part | **invalid** | valid | **invalid** |
| `joe@ex_ample.com` | underscore in domain | **invalid** | **invalid** | **invalid** |
| `joe@-example.com` | label starts with a hyphen | **invalid** | **invalid** | **invalid** |
| `joe@example..com` | empty domain label | **invalid** | **invalid** | **invalid** |
| `joe(comment)@example.com` | comment (CFWS) | **invalid** | **invalid** | **invalid** |
| `joe@example.com\n` | trailing line feed | **invalid** | **invalid** | **invalid** |
| ` joe@example.com` | leading space | **invalid** | **invalid** | **invalid** |
| `joe bloggs@example.com` | unquoted space | **invalid** | **invalid** | **invalid** |
| `a@b@example.com` | two @ | **invalid** | **invalid** | **invalid** |
| `joe` | no @ | **invalid** | **invalid** | **invalid** |
| `""` (empty) | empty string | valid | **invalid** | **invalid** |
| `a{64}@example.com` | local part of 64 characters | valid | valid | valid |
| `a{65}@example.com` | local part of 65 characters | **invalid** | valid | **invalid** |
| `joe@a{64}.com` | domain label of 64 characters | **invalid** | **invalid** | **invalid** |

### Comparison

- **Stricter than Drupal,** for both the WHATWG check and RFC 5321: UTF-8 addresses (`jöe@`, `@exämple.com`), and `""` (see [5](#5-notblank-choice-required)).
- **Looser than Drupal:**
  - WHATWG: leading, trailing and double dots in the local part; single-label domains; local parts over 64 characters.
  - RFC 5321: quoted local parts, address literals, single-label domains.
- **WHATWG differs from the standard** on 11 of 37 corpus cases, and on 15 of the 65 string cases of the JSON-Schema-Test-Suite, which the profile then has to filter out.

### Proposed resolution

- **Pin `email` to RFC 5321 Mailbox** with the size limits of RFC 5321 §4.5.3.1. That is the format the registry defines. The test suite's email cases then apply unfiltered. Decision 0003's WHATWG orientation for `email` would change.
- **`drupal:Email` named check** (`runs: server`) for Drupal's exact verdict. It covers the RFC 5321 addresses Drupal rejects: quoted, literal, single-label.
- **UTF-8 addresses: a known difference.** Clients reject them until `idn-email` is pinned. It is out of v1 for the IDNA reasons in the profile. Revisit when `idn-email` is pinned.
- **Alternative:** keep WHATWG and record the deviation from the registry in decision 0003. Its verdicts are not stricter than Drupal for ASCII addresses either.

## 2. `uri`

### Drupal

- **`uri` DataType** (config `uri`, `uri` fields, the link field's `uri` property): the `PrimitiveType` constraint only checks that `parse_url()` returns a scheme (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/PrimitiveTypeConstraintValidator.php:60`). There is no syntax check beyond what `parse_url()` rejects, such as `http://` or a non-numeric port.
- **Link field:** its constraints add semantics (`core/modules/link/src/Plugin/Field/FieldType/LinkItem.php:28-33`):
  - `LinkType` resolves the value with `Url::fromUri()` (`core/lib/Drupal/Core/Url.php:278-334`). `internal:` needs a leading slash, `entity:` an id, and `route:` a route name.
  - `LinkNotExistingInternal`: the route must exist.
  - `LinkAccess`: the current user must have access.
  - `LinkExternalProtocols`: external schemes must be in `filter_protocols` (`core/core.services.yml:51`). The check is case-sensitive, so `HTTPS://` fails (`core/modules/link/src/Plugin/Validation/Constraint/LinkExternalProtocolsConstraintValidator.php:36`).
  - Relative input such as `/node/1` or `<nolink>` is converted by `LinkWidget` before it is stored (`core/modules/link/src/Plugin/Field/FieldWidget/LinkWidget.php:108-135`).
  - An empty `uri` makes the item empty (`LinkItem.php:157-160`), so `""` is not validated at all.
- **Form API `url` element:** `UrlHelper::isValid($value, TRUE)` (`core/lib/Drupal/Core/Render/Element/Url.php:69`, `core/lib/Drupal/Component/Utility/UrlHelper.php:444-466`). It allows only `ftp`, `http`, `https` and `feed`, and only ASCII; its `$` without `D` accepts a trailing newline. The element trims the value first (`Url.php:66`).
- **Special routes:** core defines `<front>`, `<none>`, `<nolink>`, `<button>` and `<current>` (`core/modules/system/system.routing.yml:184-212`). Migrations and the link widget store `route:<nolink>`, `route:<none>` and `route:<button>` (`LinkWidget.php:520`).

### Corpus

The link field was validated as user 1.

| Value | Note | Drupal uri | Drupal link field | Drupal UrlHelper::isValid() (url element) | Portable Schema uri (= standard) | uri-reference |
|---|---|---|---|---|---|---|
| `https://example.com` |  | valid | valid | valid | valid | valid |
| `https://example.com/path?q=1#top` |  | valid | valid | valid | valid | valid |
| `HTTPS://EXAMPLE.COM/` |  | valid | **invalid** | valid | valid | valid |
| `https://user:pass@example.com:8080/` |  | valid | valid | valid | valid | valid |
| `http://[2001:db8::1]/` |  | valid | valid | valid | valid | valid |
| `ftp://ftp.example.com/file.txt` |  | valid | valid | valid | valid | valid |
| `mailto:joe@example.com` |  | valid | valid | **invalid** | valid | valid |
| `tel:+1-816-555-1212` |  | valid | valid | **invalid** | valid | valid |
| `urn:isbn:0451450523` |  | valid | **invalid** | **invalid** | valid | valid |
| `javascript:alert(1)` | scheme outside filter_protocols | valid | **invalid** | **invalid** | valid | valid |
| `internal:/node/1` | Drupal link field: internal path | valid | valid | **invalid** | valid | valid |
| `internal:/` | Drupal link field: front page | valid | valid | **invalid** | valid | valid |
| `internal:node/1` | internal: without leading slash | valid | **invalid** | **invalid** | valid | valid |
| `internal:/user/login?destination=/node` | internal path with query | valid | valid | **invalid** | valid | valid |
| `entity:node/1` | Drupal link field: entity reference | valid | valid | **invalid** | valid | valid |
| `entity:node` | entity: without an id | valid | **invalid** | **invalid** | valid | valid |
| `route:<nolink>` | Drupal link field: route | valid | valid | **invalid** | **invalid** | **invalid** |
| `route:user.login` |  | valid | valid | **invalid** | valid | valid |
| `route:does.not.exist` | route name that does not exist | valid | **invalid** | **invalid** | valid | valid |
| `base:robots.txt` | Drupal: path relative to the base URL | valid | valid | **invalid** | valid | valid |
| `/node/1` | relative reference, absolute path | **invalid** | **invalid** | **invalid** | **invalid** | valid |
| `node/1` | relative reference, relative path | **invalid** | **invalid** | **invalid** | **invalid** | valid |
| `//example.com/x` | network-path (protocol-relative) reference | **invalid** | **invalid** | **invalid** | **invalid** | valid |
| `#top` | fragment only | **invalid** | **invalid** | **invalid** | **invalid** | valid |
| `""` (empty) | empty string | **invalid** | valid | **invalid** | **invalid** | valid |
| `https://example.com/ä` | unescaped non-ASCII in the path (IRI) | valid | valid | **invalid** | **invalid** | **invalid** |
| `https://exämple.com/` | unescaped non-ASCII host (IRI) | valid | valid | **invalid** | **invalid** | **invalid** |
| `https://example.com/a b` | space in the path | valid | valid | **invalid** | **invalid** | **invalid** |
| `https://example.com/{x}` | braces in the path | valid | valid | **invalid** | **invalid** | **invalid** |
| `https://example.com/%zz` | invalid percent-encoding | valid | valid | **invalid** | **invalid** | **invalid** |
| `https://example.com:abc/` | non-numeric port | **invalid** | **invalid** | **invalid** | **invalid** | **invalid** |
| `https://example.com/\n` | trailing line feed | valid | valid | valid | **invalid** | **invalid** |
| `http://` | scheme and empty authority | **invalid** | **invalid** | **invalid** | valid | valid |
| `1http://example.com` | scheme starts with a digit | valid | **invalid** | **invalid** | **invalid** | **invalid** |
| `c:\windows\file.txt` | Windows path | valid | **invalid** | **invalid** | **invalid** | **invalid** |

### Comparison

- **Stricter than Drupal:**
  - unescaped non-ASCII (IRIs), spaces, braces;
  - invalid percent-encoding;
  - a trailing newline;
  - `1http:` and Windows paths;
  - core's own `route:<nolink>`: `<` and `>` are not allowed in a URI.
- **Looser than Drupal:** the link field semantics: allowed protocols, `internal:` without a slash, `entity:` without an id, routes that do not exist, access. These are not syntax and need the site.

### Proposed resolution

- **Keep `format: uri`** (RFC 3986) for Drupal's `uri`. Values Drupal accepts that are not URIs are a known difference, except `route:` URIs (next bullet).
- **`route:<name>`:** the integration percent-encodes `<` and `>` on export (`route:%3Cnolink%3E`) and decodes them on import. Decision 0004 already does this for datetimes.
- **Link field checks** have no mapping, so they run on the server as named checks (`drupal:LinkType`, `drupal:LinkExternalProtocols`, `drupal:LinkNotExistingInternal`, `drupal:LinkAccess`).
- **IRIs in link fields** (`https://example.com/ä`) are realistic input. Option: move `iri` / `iri-reference` out of the profile's "Not allowed in v1" list, pinned as RFC 3987 syntax without IDNA processing, and map link fields to `iri`. Syntax alone needs no IDNA tables, so PHP and JS can agree (U).

## 3. `pattern`

### Drupal

- **Engine:** the `Regex` constraint is Symfony's `RegexValidator`, i.e. `preg_match()` on the PCRE pattern as written in the schema (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/RegexConstraint.php`; symfony/validator `Constraints/RegexValidator.php:49`).
- **`""` and `null`** are skipped (symfony/validator `Constraints/RegexValidator.php:33`).
- **No core pattern uses the `D` modifier,** so `$` also matches before a final newline.
- **Patterns in core config schema:**
  - `label`, `plural_label`, `text`, mailer options and editor upload directories use `[^\PC]` with `match: false`;
  - `block.block.*:id` uses a lookahead (`core/modules/block/config/schema/block.schema.yml:17`);
  - path aliases use `/^\//i` (`core/modules/path_alias/src/Entity/PathAlias.php:66`);
  - the `configurable_language` id pattern is not anchored (`core/modules/language/config/schema/language.schema.yml:100`), so it matches almost anything.

### Corpus

ECMA-262, as is: the pattern body as a JS `RegExp` with the `u` flag. Converted: the pattern that the converter proposed below emits, with the `u` flag.

| Source | Pattern | Value | Note | Drupal (PCRE) | ECMA-262, as is | Converted pattern | ECMA-262, converted |
|---|---|---|---|---|---|---|---|
| machine_name | `/^[a-z0-9_]+$/` | `basic_html` |  | valid | valid | `^[a-z0-9_]+\n?$` | valid |
| machine_name | `/^[a-z0-9_]+$/` | `basic_html\n` | trailing line feed | valid | **invalid** | `^[a-z0-9_]+\n?$` | valid |
| machine_name | `/^[a-z0-9_]+$/` | `basic_html\n\n` | two trailing line feeds | **invalid** | **invalid** | `^[a-z0-9_]+\n?$` | **invalid** |
| machine_name | `/^[a-z0-9_]+$/` | `""` (empty) | empty string | valid | **invalid** | `^[a-z0-9_]+\n?$` | **invalid** |
| color_hex | `/^[#]?([0-9a-fA-F]{3}){1,2}$/` | `#ff0000` |  | valid | valid | `^[#]?([0-9a-fA-F]{3}){1,2}\n?$` | valid |
| color_hex | `/^[#]?([0-9a-fA-F]{3}){1,2}$/` | `#ff0000\n` | trailing line feed | valid | **invalid** | `^[#]?([0-9a-fA-F]{3}){1,2}\n?$` | valid |
| block.block.*:id | `/^(?!\.)[a-z0-9_.]+$/` | `olivero.page_title` |  | valid | valid | `^(?!\.)[a-z0-9_.]+\n?$` | valid |
| block.block.*:id | `/^(?!\.)[a-z0-9_.]+$/` | `.hidden` | leading dot, rejected by the lookahead | **invalid** | **invalid** | `^(?!\.)[a-z0-9_.]+\n?$` | **invalid** |
| block.block.*:id | `/^(?!\.)[a-z0-9_.]+$/` | `olivero.page_title\n` | trailing line feed | valid | **invalid** | `^(?!\.)[a-z0-9_.]+\n?$` | valid |
| path_alias:alias | `/^\//i` | `/about` |  | valid | valid | `^\/` | valid |
| path_alias:alias | `/^\//i` | `about` |  | **invalid** | **invalid** | `^\/` | **invalid** |
| configurable_language:id | `/[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*/` | `de` |  | valid | valid | `[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*` | valid |
| configurable_language:id | `/[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*/` | `123 not a langcode!` | pattern is not anchored | valid | valid | `[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*` | valid |
| label | `/([^\PC])/u` (match: false) | `Hello world` |  | valid | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | valid |
| label | `/([^\PC])/u` (match: false) | `Hello\tworld` | tab (Cc) | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | **invalid** |
| label | `/([^\PC])/u` (match: false) | `Hello\nworld` | line feed (Cc) | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | **invalid** |
| label | `/([^\PC])/u` (match: false) | `Schifffahrts\u00adgesellschaft` | soft hyphen U+00AD (Cf) | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | **invalid** |
| label | `/([^\PC])/u` (match: false) | `zero\u200bwidth` | zero width space U+200B (Cf) | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | **invalid** |
| label | `/([^\PC])/u` (match: false) | `Family 👨\u200d👩\u200d👧` | emoji ZWJ sequence, U+200D (Cf) | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | **invalid** |
| label | `/([^\PC])/u` (match: false) | `Flag 🇦🇹` | regional indicators (So) | valid | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | valid |
| label | `/([^\PC])/u` (match: false) | `Tired 🫩` | U+1FAE9, assigned in Unicode 16.0 | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | valid |
| label | `/([^\PC])/u` (match: false) | `Private \ue000` | private use U+E000 (Co) | **invalid** | SyntaxError | `^[^\p{Cc}\p{Cf}\p{Co}]*$` | **invalid** |
| text | `/([^\PC\x09\x0a\x0d])/u` (match: false) | `Line 1\r\nLine 2\twith tab` |  | valid | SyntaxError | `^(?:[\x09\x0a\x0d]\|[^\p{Cc}\p{Cf}\p{Co}])*$` | valid |
| text | `/([^\PC\x09\x0a\x0d])/u` (match: false) | `Bell \u0007` | BEL (Cc) | **invalid** | SyntaxError | `^(?:[\x09\x0a\x0d]\|[^\p{Cc}\p{Cf}\p{Co}])*$` | **invalid** |

### Comparison

- **Trailing newline:** Drupal accepts `basic_html\n`, `#ff0000\n`, `olivero.page_title\n`; ECMA-262 does not.
- **`""`** passes every `Regex` constraint in Drupal.
- **`[^\PC]`** is a syntax error in ECMA-262 with `u`: `\P` needs braces. Without `u`, `\P` is an identity escape and the pattern means something else.
- **Unicode versions:** PCRE2 10.42 uses Unicode 14 and V8 13.6 uses Unicode 17. U+1FAE9 (Unicode 16) is unassigned (`Cn`, part of `C`) for PCRE, so Drupal rejects the label `Tired 🫩`. V8 doesn't match U+1FAE9 with `\p{C}`, so the label passes there.
- **Drupal's labels** reject tabs, soft hyphens and emoji ZWJ sequences (U+200D is `Cf`).
- **Lookarounds** give the same verdicts in both engines. Whether they belong to the portable subset is the planned Patterns section.

### Proposed resolution

- **Keep ECMA-262 semantics** for `pattern` (JSON Schema Validation §6.3.3).
- **The PHP implementation** matches ECMA patterns with `u` and `D`, after translating syntax where PCRE differs, so that PHP and JS agree.
- **The converter**
  - strips the PCRE delimiters and modifiers (`i` becomes the `i` flag);
  - rewrites a final `$` of a pattern without `D` to `\n?$`, which is what PCRE's `$` matches. That keeps the trailing-newline behaviour, so Portable Schema is not stricter;
  - turns `[^\PC…]` with `match: false` into the categories that don't depend on the Unicode version, plus the characters the class allows. `label` becomes `^[^\p{Cc}\p{Cf}\p{Co}]*$`, `text` becomes `^(?:[\x09\x0a\x0d]|[^\p{Cc}\p{Cf}\p{Co}])*$`, `plural_label` the same with `\x03`. In the corpus they give Drupal's verdicts, except for the unassigned code point (U: `Cf` membership is assumed stable enough);
  - turns other `match: false` patterns into `not: {pattern}`.
- **Unassigned code points** (`Cn`) depend on the Unicode version, so they stay a server-only check.
- **`""`:** see [5](#5-notblank-choice-required).
- **Alternative for the trailing newline:** treat it as a Drupal bug and propose `D` for core's patterns. Portable Schema would then reject `basic_html\n` until core changes.

## 4. String length

### Drupal

- **`Length`** is core's `LengthConstraint`, which only changes messages. Symfony's `LengthValidator` validates it, with the default `countUnit: codepoints`, i.e. `mb_strlen()` (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/LengthConstraint.php`; symfony/validator `Constraints/Length.php:59`, `Constraints/LengthValidator.php:54-58`). No core code sets `countUnit`.
- **Form API `#maxlength`** also uses `mb_strlen()` (`core/lib/Drupal/Core/Form/FormValidator.php:340`).
- **String fields** add `Length` from `max_length` (`core/lib/Drupal/Core/Field/Plugin/Field/FieldType/StringItem.php:75`).

### Corpus

`max` is the limit used for both `Length` and `maxLength`.

| Value | Note | Code points | Graphemes (PHP / JS) | UTF-16 units | Bytes | max | Drupal Length | Portable maxLength |
|---|---|---|---|---|---|---|---|---|
| `abc` |  | 3 | 3 / 3 | 3 | 3 | 3 | valid | valid |
| `ä` | precomposed U+00E4 | 1 | 1 / 1 | 1 | 2 | 1 | valid | valid |
| `ä` | a + combining diaeresis U+0308 | 2 | 1 / 1 | 2 | 3 | 1 | **invalid** | **invalid** |
| `é́` | e + two combining acute accents | 3 | 1 / 1 | 3 | 5 | 1 | **invalid** | **invalid** |
| `😀` | one astral code point U+1F600 | 1 | 1 / 1 | 2 | 4 | 1 | valid | valid |
| `👍🏽` | emoji + skin tone modifier | 2 | 1 / 1 | 4 | 8 | 1 | **invalid** | **invalid** |
| `👨\u200d👩\u200d👧` | ZWJ family sequence | 5 | 1 / 1 | 8 | 18 | 1 | **invalid** | **invalid** |
| `🇦🇹` | flag: two regional indicators | 2 | 1 / 1 | 4 | 8 | 1 | **invalid** | **invalid** |
| `한` | conjoining Hangul jamo | 3 | 1 / 1 | 3 | 9 | 1 | **invalid** | **invalid** |
| `नमस्ते` | Devanagari with virama | 6 | 3 / 3 | 6 | 18 | 3 | **invalid** | **invalid** |
| `\r\n` | CR LF | 2 | 1 / 1 | 2 | 2 | 1 | **invalid** | **invalid** |

### Proposed resolution

- **No change:** Drupal and `maxLength` count code points.
- **Note for widgets:** the HTML `maxlength` attribute counts UTF-16 code units (U), so it is stricter for astral characters, such as emoji: 😀 is one code point and two units. JS widgets count code points instead.
- **Graphemes:** Drupal doesn't count them. `countUnit: graphemes` would need a mapping of its own, not `maxLength`.

## 5. `NotBlank`, `Choice`, `required`

### Drupal

- **`NotBlank`** is Symfony's (`core/lib/Drupal/Core/Validation/ConstraintManager.php:114`). It rejects `false` and every empty value except `"0"`, `0` and `0.0`: `""`, `null`, `[]` (symfony/validator `Constraints/NotBlankValidator.php:38`). `allowNull: true` lets `null` pass; core uses it, e.g., in `editor.schema.yml`.
- **`Choice`** is Symfony's, strict by default (symfony/validator `Constraints/Choice.php:39`): `in_array(..., true)`. It skips `null`. `AllowedValues`, used by option fields, is also strict, but first casts the choices to the value's type (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/AllowedValuesConstraintValidator.php:103`); that was read from the code, not run.
- **`Length`, `Regex`, `Email`, `Uuid` and `Choice` skip `null`;** `Email`, `Uuid` and `Regex` also skip `""`.
- **`requiredKey`** is only enforced when the config root type has `FullyValidatable`, or, below a dynamic type, the resolved type (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/ValidKeysConstraintValidator.php:94-152`). The same holds for non-null values (`core/lib/Drupal/Core/Config/TypedConfigManager.php:130-187`, which adds `NotNull` through `setRequired()`). Core's non-test schema files contain 84 `FullyValidatable` entries.
- **Config objects with translatable values** need `langcode` (`LangcodeRequiredIfTranslatableValues` on `config_object`, `core.data_types.schema.yml:235`). A `langcode` without translatable values only triggers a deprecation.
- **Config entities** need `langcode` only as a required key of a `FullyValidatable` type. The validator has a config-entity branch (`core/lib/Drupal/Core/Config/Plugin/Validation/Constraint/LangcodeRequiredIfTranslatableValuesConstraintValidator.php:43`), but `config_entity` is a `mapping` without the constraint (`core.data_types.schema.yml:424-425`), so the branch doesn't apply.

### Corpus

| JSON | Drupal NotBlank | Drupal NotBlank (allowNull) | Portable: string + minLength 1 | Portable: per type |
|---|---|---|---|---|
| `"a"` | valid | valid | valid | valid |
| `""` | **invalid** | **invalid** | **invalid** | **invalid** |
| `" "` | valid | valid | valid | valid |
| `"0"` | valid | valid | valid | valid |
| `0` | valid | valid | **invalid** | valid |
| `false` | **invalid** | **invalid** | **invalid** | **invalid** |
| `null` | **invalid** | valid | **invalid** | **invalid** |
| `[]` | **invalid** | **invalid** | **invalid** | **invalid** |
| `{}` | **invalid** | **invalid** | **invalid** | **invalid** |

| JSON | Drupal Choice [0, 1, 2] | Portable enum [0, 1, 2] |
|---|---|---|
| `1` | valid | valid |
| `"1"` | **invalid** | **invalid** |
| `1.0` | **invalid** | valid |
| `true` | **invalid** | **invalid** |
| `3` | **invalid** | **invalid** |
| `null` | valid | **invalid** |

The empty string against config schema types, Portable Schema with the mapped `portable:*` type:

| Config schema type | Drupal | Portable Schema |
|---|---|---|
| email | valid | **invalid** |
| uuid | valid | **invalid** |
| machine_name | valid | **invalid** |
| color_hex | valid | **invalid** |
| uri | **invalid** | **invalid** |
| langcode | **invalid** | **invalid** |
| required_label | **invalid** | **invalid** |

Config objects through `TypedConfigManager::createFromNameAndData()->validate()`. Portable Schema: the schema as `docs/drupal.md` maps it today, with every key required unless `requiredKey: false` and `null` only with `nullable: true`.

| Config | Case | Drupal | Drupal violations | Portable Schema |
|---|---|---|---|---|
| system.site | complete, mail_notification: null as in core system.site.yml | valid |  | **invalid** |
| system.site | keys missing; type is not FullyValidatable | valid |  | **invalid** |
| system.site | null values; type is not FullyValidatable | valid |  | **invalid** |
| system.maintenance | complete | valid |  | valid |
| system.maintenance | key missing; type is FullyValidatable | **invalid** | 'message' is a required key. | **invalid** |
| system.maintenance | null value; type is FullyValidatable | **invalid** | message: This value should not be null. | **invalid** |
| system.maintenance | langcode missing (requiredKey: false in config_object) | **invalid** | The system.maintenance config object must specify a language code, because it contains translatable values. | valid |
| system.cron | complete | valid |  | valid |
| system.cron | langcode present without translatable values (only a deprecation notice) | valid |  | valid |
| core.entity_view_mode.node.full | config entity with a translatable label, without langcode; type is not FullyValidatable | valid |  | **invalid** |

### Proposed resolution

- **`NotBlank` per JSON type:**
  - strings: `minLength: 1`;
  - arrays: `minItems: 1`;
  - objects: `minProperties: 1`;
  - booleans: `const: true`;
  - numbers: nothing;
  - `null` is excluded unless `allowNull`, even when the value is `nullable`.
  `required` is a different thing: it is about the key, not the value.
- **`Choice`** maps to `enum`. When `null` is allowed (`nullable: true`, or any scalar in a type that is not `FullyValidatable`), the converter adds `null` to the `enum`: `type: ["integer", "null"]` alone doesn't let `null` through an `enum`. Strictness: Drupal rejects the JSON `1.0` for the choice `1`; Portable Schema accepts it, which is looser and fine.
- **`required` and `nullable` follow `FullyValidatable`:**
  - **`FullyValidatable` types** (the root type, or the resolved type below a dynamic type): `required` for every key without `requiredKey: false`; `null` only with `nullable: true`.
  - **Other types:** no `required`, and `null` allowed for scalar values. Example: core's own `system.site.yml` has `mail_notification: null` without `nullable` and is valid in Drupal.
  - **`langcode`** goes into `required` when a `config_object` type has translatable values; otherwise the `FullyValidatable` rule applies.
- **Empty strings:** where a Drupal constraint skips `""` (`Email`, `Uuid`, `Regex`) and nothing else forbids it, the converter emits `anyOf: [{"const": ""}, {"$ref": "portable:email"}]`. The standard formats stay unchanged. Widgets and adapters key on `format` and `type` (types/README.md), so they need to look through this `anyOf`. Alternative: the integration turns `""` into an absent key where the key is optional.

## 6. Numbers

### Drupal

- **`integer`:** `PrimitiveType` checks `filter_var($value, FILTER_VALIDATE_INT)` (`core/lib/Drupal/Core/Validation/Plugin/Validation/Constraint/PrimitiveTypeConstraintValidator.php:46`). The range is PHP's: ±2^63. Numeric strings with spaces or `+`, integral floats and `true` pass.
- **`float`:** `FILTER_VALIDATE_FLOAT` (`:43`).
- **`decimal`:** `preg_match('/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/i', $value)` (`:49`), without `D`. JSON numbers pass as PHP numbers, except floats that PHP prints with an exponent, such as `1.0E-7`.
- **`DecimalData` JSON Schema:** core emits `{"type": "string", "format": "number"}` (`core/lib/Drupal/Core/TypedData/Plugin/DataType/DecimalData.php:26`). `number` is not in the format registry.
- **`weight`:** `Range` of 32-bit integers, like `int32` (`core.data_types.schema.yml:185-194`).

### Corpus

Values are JSON text, decoded with `json_decode()` and `JSON.parse()`.

| Type | JSON | Note | PHP value | JS value | Drupal | Portable Schema | Standard |
|---|---|---|---|---|---|---|---|
| integer | `1` |  | `int 1` | `1` | valid | valid | valid |
| integer | `-1` |  | `int -1` | `-1` | valid | valid | valid |
| integer | `1.0` | integral number with a fraction part | `float 1.0` | `1` | valid | valid | valid |
| integer | `1.5` |  | `float 1.5` | `1.5` | **invalid** | **invalid** | **invalid** |
| integer | `1e3` | exponent notation | `float 1000.0` | `1000` | valid | valid | valid |
| integer | `"42"` | numeric string | `string '42'` | `"42"` | valid | **invalid** | **invalid** |
| integer | `" 42 "` | numeric string with spaces | `string ' 42 '` | `" 42 "` | valid | **invalid** | **invalid** |
| integer | `"+42"` |  | `string '+42'` | `"+42"` | valid | **invalid** | **invalid** |
| integer | `9007199254740991` | 2^53 - 1 | `int 9007199254740991` | `9007199254740991` | valid | valid | valid |
| integer | `9007199254740992` | 2^53 | `int 9007199254740992` | `9007199254740992` | valid | **invalid** | valid |
| integer | `9007199254740993` | 2^53 + 1, not representable as a double | `int 9007199254740993` | `9007199254740992` | valid | **invalid** | valid |
| integer | `9223372036854775807` | PHP_INT_MAX | `int 9223372036854775807` | `9223372036854776000` | valid | **invalid** | valid |
| integer | `9223372036854775808` | PHP_INT_MAX + 1 | `float 9.223372036854776E+18` | `9223372036854776000` | **invalid** | **invalid** | valid |
| integer | `-9223372036854775808` | PHP_INT_MIN | `int -9223372036854775807-1` | `-9223372036854776000` | valid | **invalid** | valid |
| integer | `true` |  | `bool true` | `true` | valid | **invalid** | **invalid** |
| decimal | `"123.45"` |  | `string '123.45'` | `"123.45"` | valid | valid | n/a |
| decimal | `"-0.5"` |  | `string '-0.5'` | `"-0.5"` | valid | valid | n/a |
| decimal | `"+1.5"` | leading plus | `string '+1.5'` | `"+1.5"` | valid | **invalid** | n/a |
| decimal | `"1."` | no digits after the point | `string '1.'` | `"1."` | valid | **invalid** | n/a |
| decimal | `".5"` | no digits before the point | `string '.5'` | `".5"` | valid | **invalid** | n/a |
| decimal | `"007"` | leading zeros | `string '007'` | `"007"` | valid | **invalid** | n/a |
| decimal | `"1e5"` | exponent | `string '1e5'` | `"1e5"` | **invalid** | **invalid** | n/a |
| decimal | `"1.5\n"` | trailing line feed | `string '1.5\n'` | `"1.5\n"` | valid | **invalid** | n/a |
| decimal | `""` | empty string | `string ''` | `""` | **invalid** | **invalid** | n/a |
| decimal | `123.45` | JSON number | `float 123.45` | `123.45` | valid | **invalid** | n/a |
| decimal | `10` | JSON integer | `int 10` | `10` | valid | **invalid** | n/a |
| decimal | `1e-7` | JSON number in exponent notation | `float 1.0E-7` | `1e-7` | **invalid** | **invalid** | n/a |
| float | `1.5` |  | `float 1.5` | `1.5` | valid | valid | valid |
| float | `1e300` |  | `float 1.0E+300` | `1e+300` | valid | valid | valid |
| float | `"1.5"` | numeric string | `string '1.5'` | `"1.5"` | valid | **invalid** | **invalid** |
| float | `"1e3"` | numeric string with exponent | `string '1e3'` | `"1e3"` | valid | **invalid** | **invalid** |
| float | `"abc"` |  | `string 'abc'` | `"abc"` | **invalid** | **invalid** | **invalid** |

### Proposed resolution

- **Integers beyond 2^53:** JS can't represent them, e.g. `JSON.parse("9007199254740993")` is `9007199254740992`. Keep `portable:integer` in the I-JSON range and document the difference. It matters for `size: big` integer fields (U: core's own IDs and timestamps don't get there).
- **`decimal`:** the registry gives no grammar ("A fixed point decimal number of unspecified precision and range") and allows strings and numbers.
  - Widen the pinned grammar to Drupal's syntax: `^[+-]?([0-9]+(\.[0-9]*)?|\.[0-9]+)$`. Decision 0004 prefers normalizing in the integration over system-specific variants in the library. Widening fits better here: the registry pins no grammar, and client input such as `.5` meets the client validator before any integration could normalize it.
  - The format accepts JSON numbers, as the registry allows; `portable:decimal` stays `type: string`.
  - The integration turns numbers into strings on export.
- **Numeric strings and booleans** for integers and floats belong to the planned normalization step (cast table), not to the types.
- **Upstream:** core's `DecimalData` should emit `format: decimal` (see [Open questions](#open-questions)).

## 7. `uuid`, `color_hex`, `machine_name`, `langcode`

### Drupal

All values below went through typed config with the config schema type (`core/config/schema/core.data_types.schema.yml`):

- **`uuid`** (`:125-129`): Symfony `Uuid` with the defaults `strict: true` and versions 1–8. It checks the version digit and the RFC variant (symfony/validator `Constraints/UuidValidator.php:162-255`) and is case-insensitive. The nil and max UUIDs are rejected, although RFC 9562 defines them.
- **`color_hex`** (`:139-153`): `Regex` `/^[#]?([0-9a-fA-F]{3}){1,2}$/`.
- **`machine_name`** (`:156-165`): `Regex` `/^[a-z0-9_]+$/` and `Length` max 166.
- **`langcode`** (`:168-174`): `NotNull` and `Choice` with the callback `LanguageReference::getAllValidLangcodes()` (`core/lib/Drupal/Core/TypedData/Plugin/DataType/LanguageReference.php:48-60`). Its values: core's standard language list, the site's languages, and `zxx`, `und`, `system`, `site_default`, `x-default`. Custom languages are checked against `LanguageInterface::VALID_LANGCODE_REGEX`, `[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*` (`core/modules/language/src/Form/LanguageFormBase.php:94`).
- **`label` and `text`** (`:69-79`, `:107-122`): no control characters, see [3](#3-pattern).

### Corpus

Portable Schema for `langcode`: `portable:langcode` plus the catalog of this site's `getAllValidLangcodes()` (112 values).

| Type | Value | Note | Drupal | Portable Schema | Standard |
|---|---|---|---|---|---|
| uuid | `6e4a8f3c-1a2b-4c3d-8e9f-0a1b2c3d4e5f` | version 4 | valid | valid | valid |
| uuid | `6E4A8F3C-1A2B-4C3D-8E9F-0A1B2C3D4E5F` | upper case | valid | valid | valid |
| uuid | `01890a5d-ac96-774b-bcce-b302099a8057` | version 7 | valid | valid | valid |
| uuid | `00000000-0000-0000-0000-0{12}` | nil UUID | **invalid** | valid | valid |
| uuid | `ffffffff-ffff-ffff-ffff-f{12}` | max UUID | **invalid** | valid | valid |
| uuid | `99c17cbb-656f-f64a-940f-1a4568f03487` | version nibble f | **invalid** | valid | valid |
| uuid | `2eb8aa08-aa98-11ea-f4aa-73b441d16380` | variant nibble f | **invalid** | valid | valid |
| uuid | `6e4a8f3c1a2b4c3d8e9f0a1b2c3d4e5f` | no hyphens | **invalid** | **invalid** | **invalid** |
| uuid | `{6e4a8f3c-1a2b-4c3d-8e9f-0a1b2c3d4e5f}` | braces | **invalid** | **invalid** | **invalid** |
| uuid | `6e4a8f3c-1a2b-4c3d-8e9f-0a1b2c3d4e5f\n` | trailing line feed | **invalid** | **invalid** | **invalid** |
| uuid | `""` (empty) | empty string | valid | **invalid** | **invalid** |
| color_hex | `#ff0000` |  | valid | valid | n/a |
| color_hex | `ff0000` | without # | valid | valid | n/a |
| color_hex | `#F00` | short, upper case | valid | valid | n/a |
| color_hex | `#ff000080` | with alpha | **invalid** | **invalid** | n/a |
| color_hex | `#ff0000\n` | trailing line feed | valid | **invalid** | n/a |
| color_hex | `""` (empty) | empty string | valid | **invalid** | n/a |
| machine_name | `basic_html` |  | valid | valid | n/a |
| machine_name | `Basic_HTML` | upper case | **invalid** | **invalid** | n/a |
| machine_name | `basic-html` | hyphen | **invalid** | **invalid** | n/a |
| machine_name | `basic_html\n` | trailing line feed | valid | **invalid** | n/a |
| machine_name | `""` (empty) | empty string | valid | **invalid** | n/a |
| machine_name | `a{167}` | 167 characters | **invalid** | **invalid** | n/a |
| langcode | `en` |  | valid | valid | valid |
| langcode | `pt-br` |  | valid | valid | valid |
| langcode | `zh-hans` |  | valid | valid | valid |
| langcode | `gsw-berne` |  | valid | valid | valid |
| langcode | `en-x-simple` |  | valid | valid | valid |
| langcode | `und` | LANGCODE_NOT_SPECIFIED | valid | valid | valid |
| langcode | `zxx` | LANGCODE_NOT_APPLICABLE | valid | valid | valid |
| langcode | `x-default` | LANGCODE_DEFAULT | valid | **invalid** | valid |
| langcode | `site_default` | LANGCODE_SITE_DEFAULT | valid | **invalid** | **invalid** |
| langcode | `EN` | upper case | **invalid** | **invalid** (pattern alone: valid) | valid |
| langcode | `pt_BR` | underscore | **invalid** | **invalid** | **invalid** |
| langcode | `tlh` | well-formed BCP 47, not in Drupal's list | **invalid** | **invalid** (pattern alone: valid) | valid |
| langcode | `""` (empty) | empty string | **invalid** | **invalid** | **invalid** |
| label | `Main navigation` |  | valid | valid | n/a |
| label | `Tab\there` | tab | **invalid** | valid | n/a |
| label | `Line\nbreak` | line feed | **invalid** | **invalid** | n/a |
| label | `Family 👨\u200d👩\u200d👧` | emoji ZWJ sequence | **invalid** | valid | n/a |
| label | `Tired 🫩` | U+1FAE9, Unicode 16.0 | **invalid** | valid | n/a |
| text | `Line 1\nLine 2` |  | valid | valid | n/a |
| text | `Bell \u0007` | BEL | **invalid** | valid | n/a |
| weight | `2147483647` |  | valid | valid | n/a |
| weight | `2147483648` |  | **invalid** | **invalid** | n/a |

### Proposed resolution

- **`uuid`:** no change. Drupal is stricter (versions, variant), which the server enforces. `""`: see [5](#5-notblank-choice-required).
- **`color_hex`, `machine_name`:** keep `portable:color` and `portable:identifier`. The `\n?$` rewrite of [3](#3-pattern) only applies to raw `Regex` patterns; the generic types don't carry PCRE's trailing-newline quirk, so `#ff0000\n` and `basic_html\n` stay a known difference. Alternative: `\n?$` in both types. `""`: see [5](#5-notblank-choice-required).
- **`langcode`:**
  - Widen `portable:langcode` to RFC 5646 well-formed tags. That admits `x-default` (private use), 4–8 letter primary subtags and grandfathered tags.
  - `language_reference` keeps `portable:langcode` + the language catalog, as in docs/drupal.md.
  - `site_default` is a Drupal keyword, not a language tag. Config `langcode` therefore becomes `anyOf: [{"$ref": "portable:langcode"}, {"const": "site_default"}]` + the catalog, which includes `site_default`.
  - `VALID_LANGCODE_REGEX` also matches a few codes that aren't well-formed BCP 47, such as `a-b`. A site with such a custom language would fail the widened pattern; the catalog lists the code either way (U: not tried in the language form).
- **`label`, `text`:** Portable Schema is looser, which is fine. For parity, see the stable pattern in [3](#3-pattern).
- **`weight`:** no change.

## Open questions

- **Core issues** (upstream first):
  - `DecimalData`'s `#[JsonSchema]` emits `format: number`, which is not in the registry; `format: decimal` is;
  - `label` and the other `[^\PC]` patterns reject emoji ZWJ sequences and soft hyphens;
  - the `configurable_language` id pattern is not anchored.
