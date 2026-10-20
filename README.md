# Portable Schema

A portable, typed [JSON Schema](https://json-schema.org) dialect: one schema, the same validation in PHP and JS.

Status: early planning. Nothing here is stable yet.

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

## Layout

| Path | Contents |
|---|---|
| `spec/` | The normative spec: meta-schemas under `spec/v1/` and prose per topic |
| `types/` | The generic type library |
| `fixtures/conformance/` | Conformance cases that both implementations must pass |
| `fixtures/examples/` | Worked examples used for planning, e.g. schemas an integration would generate |
| `php/` | PHP implementation (`fago/portable-schema`) |
| `js/` | JS implementation (`portable-schema` on npm) |

## License

MIT, see [LICENSE](LICENSE).
