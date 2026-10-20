# Portable Schema

A portable, typed [JSON Schema](https://json-schema.org) dialect: one schema, the same validation in PHP and JS.

Status: early planning. Nothing here is stable yet.

## What it provides

- **JSON Schema based:** every Portable Schema is a valid JSON Schema 2020-12 document, for maximum interoperability. Generic tools validate the standard part; the added keywords need a Portable Schema validator.
- **Serialization-agnostic:** validates the data model: JSON, YAML, or PHP and JS values.
- **Dynamic types:** a value's schema is selected by another value, e.g. plugin settings by plugin ID.
- **Catalogs:** values checked against system facts (plugins, bundles, fields, modules), live or from a schema dump.
- **First-class PHP and JS support, with parity:** identical verdicts and errors in both.
- **Usable error messages:** one per problem, located, coded and translatable.

## What it enables

- **Generated forms,** on the PHP side (e.g. Drupal Form API) or the JS side (e.g. Vue or React), validating exactly like the server.
- **Generative UIs:** interfaces built from the schema at runtime.
- **Agents that fully understand, write and validate data,** locally and offline.
- **Typed APIs and components:** API contracts, component props and TypeScript types from the same schema.

## Principles

- **Open to more languages.** PHP and JS are supported; the spec and fixtures are language-neutral, so further implementations are possible.
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
| [JSON-Schema-Test-Suite](https://github.com/json-schema-org/JSON-Schema-Test-Suite) and [Bowtie](https://bowtie.report) | De-facto | Fixture format; the suite filtered to the profile as part of conformance; Bowtie as oracle for base libraries |

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

**Dependency note:** the PHP implementation builds on [opis/json-schema](https://github.com/opis/json-schema), which is licensed under Apache-2.0. Apache-2.0 is compatible with GPLv3 but not with GPLv2. Combined with GPL-2.0-or-later software such as Drupal, a distribution that includes it can therefore only be distributed under GPLv3 (see the [Drupal licensing FAQ](https://www.drupal.org/about/licensing)). Using it in a site or a contrib module is fine; adding it to Drupal core would need a GPLv2-compatible PHP base.

**To do:** before any core proposal, clarify the licensing with the Drupal Licensing Working Group, or replace opis/json-schema.
