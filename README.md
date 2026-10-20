# Portable Schema

A portable, typed [JSON Schema](https://json-schema.org) dialect: one schema, the same validation in PHP and JS.

Status: early planning. Nothing here is stable yet.

## Goals

- **One schema, the same verdict everywhere.** A schema validates data with the same result and the same errors in PHP and JS. Shared fixtures prove it.
- **PHP and JS, open to more.** PHP and JS are the supported languages. The spec and fixtures are language-neutral, so implementations in further languages are possible.
- **Enough metadata to generate the rest.** Schemas carry the information needed to generate forms, UIs, agent tool definitions, TypeScript types and API contracts:
  - named, reusable types;
  - translatability;
  - UI hints;
  - data sources.
- **Human, localized error messages.** Errors are structured (location, code, parameters) and shaped to one error per problem. A shared formatter turns them into messages using the schema's labels; hosts translate them with their own system, e.g. Drupal `t()` or vue-i18n. The same text in PHP and JS.
- **Schemas that depend on values.** Types selected by a data value from an open set, computed narrowing and cross-field rules. All of them can only narrow what is valid.
- **System facts as data.** References to things a system provides (plugins, bundles, fields, modules) are checked against catalogs. Catalogs come from the live system or from a schema dump, so validation can also run offline.
- **Stay standard.** Every Portable Schema is valid JSON Schema 2020-12. Generic validators ignore the added keywords and still check the structure; a standard-only export serves tools that need plain JSON Schema.
- **Aligned with the next JSON Schema release.** The profile already follows its rules, so moving to it needs no schema changes:
  - `x-` keywords are annotations only;
  - unknown keywords are an error;
  - `format` is an assertion;
  - results use the new output formats.
  Where the next release standardizes something we need, such as keyword namespacing or vocabularies, we adopt it instead of keeping our own.
- **Generic, driven by Drupal's needs.** Drupal's needs drive the design. But Portable Schema is not only for Drupal and has no Drupal dependency: it describes and validates data for any system. Integrations generate their types and catalogs and provide backend checks.

## What it is

Portable Schema is a dialect of JSON Schema 2020-12:

- **Profile:** the standard keywords it allows, with fixed rules where the spec leaves a choice, so PHP and JS behave the same.
- **Vocabulary:** a few keywords it adds:
  - `portable-typeBy`: type selected by a data value;
  - `portable-refine`: computed narrowing;
  - `portable-check`: backend check;
  - `portable-compare`: cross-field rule;
  - `x-portable-*` annotations.
- **Type library:** generic, reusable types with stable IDs.
- **Fixtures:** language-neutral conformance cases. The spec plus the fixtures define correct behaviour, not either implementation.
- **Reference validators** in PHP and JS that both pass the fixtures.

Every Portable Schema is valid JSON Schema. Generic validators ignore the added keywords and still check the structure.

Portable Schema itself is not tied to any framework. Integrations, such as one for Drupal, generate their types and catalogs from the system they describe.

## Built on

| Standard | Status | Used for |
|---|---|---|
| [JSON Schema 2020-12](https://json-schema.org/specification) (Core, Validation) | Current release | Base dialect |
| [JSON Schema next release](https://github.com/json-schema-org/json-schema-spec) | In progress, IETF `jsonschema` working group | Alignment of the profile: `x-` annotations, unknown keywords, `format` |
| [JSON Schema output formats](https://github.com/json-schema-org/json-schema-spec/tree/main/specs/output) | Separate spec of the next release | Result format |
| [JSON Schema vocabularies](https://github.com/json-schema-org/json-schema-spec/blob/main/specs/proposals/vocabularies.md) | Proposal, extracted from core | Declaring the added keywords |
| [JSON Schema format registry](https://github.com/json-schema-org/json-schema-spec/blob/main/specs/registries/format.json) | Registry of the next release | Allowed `format` values |
| [JSON Pointer, RFC 6901](https://www.rfc-editor.org/rfc/rfc6901) | Standard | Error and instance locations |
| [Relative JSON Pointer](https://datatracker.ietf.org/doc/html/draft-hha-relative-json-pointer) | Internet-Draft | `source` in `portable-typeBy` and `portable-refine`, `pointer` in `portable-compare` |
| [URI Template, RFC 6570](https://www.rfc-editor.org/rfc/rfc6570) | Standard | `href` in `portable-typeBy` |
| [JSON Hyper-Schema 2019-09](https://json-schema.org/draft/2019-09/json-schema-hypermedia) | Dormant | Shape of `x-portable-links` (Link Description Object) |
| [JSON, RFC 8259](https://www.rfc-editor.org/rfc/rfc8259) and [I-JSON, RFC 7493](https://www.rfc-editor.org/rfc/rfc7493) | Standards | Data model; number range, no duplicate keys |
| [RFC 3339](https://www.rfc-editor.org/rfc/rfc3339) | Standard | Dates and times |
| [YAML 1.2](https://yaml.org/spec/1.2.2/) core schema | Standard | YAML input |
| [JSON-Schema-Test-Suite](https://github.com/json-schema-org/JSON-Schema-Test-Suite) format and [Bowtie](https://bowtie.report) | De-facto | Fixture format, conformance runs |

## Layout

| Path | Contents |
|---|---|
| `spec/` | The normative spec: meta-schemas under `spec/v1/` and prose per topic |
| `types/` | The generic type library |
| `messages/` | The default message catalog (English source texts) |
| `fixtures/conformance/` | Conformance cases that both implementations must pass |
| `fixtures/examples/` | Worked examples used for planning, e.g. schemas an integration would generate |
| `php/` | PHP implementation (`fago/portable-schema`) |
| `js/` | JS implementation (`portable-schema` on npm) |
| `docs/` | Non-normative documentation, e.g. [scope](docs/scope.md) |

## License

MIT, see [LICENSE](LICENSE).
