# Drupal showcase: SDC hero

The props of a single-directory component, described with the library's shared types: `portable:label`, `portable:html`, `portable:image` and `portable:link`. Drupal core validates SDC props with JSON Schema draft-04, so the showcase also exports them as draft-04.

| File | Contents |
|---|---|
| [`hero/hero.component.yml`](hero/hero.component.yml) | The component's metadata; its `props` reference the shared types |
| [`schemas/sdc.portable_showcase.hero.json`](schemas/sdc.portable_showcase.hero.json) | The same props as a registry schema, `drupal:sdc.portable_showcase.hero`. A test checks that both match |
| [`hero.draft-04.json`](hero.draft-04.json) | The draft-04 export from `Exporter::draft04()` / `Exporter.draft04()`, identical in PHP and JS |
| `hero*.json` (except the export), `hero*.test.json` | The cases: one set of props each |

## Mapping

| Prop | Portable Schema |
|---|---|
| `heading` (required) | `portable:label` + `minLength: 1` |
| `text` | `portable:html` |
| `image` | `portable:image`; the use site requires `alt` and makes it non-empty |
| `cta` | `portable:link` |

`image` and `link` are open object types: `src` / `uri` are required, other keys are allowed, and use sites add constraints.

## Draft-04 export

The export inlines the referenced types, merges the use-site constraints and converts 2020-12 keywords: `unevaluatedProperties` → `additionalProperties`, `const` and `enum` → `enum` with the values both allow (`{"not": {}}` if none), `exclusiveMinimum`/`exclusiveMaximum` → the boolean draft-04 form, `prefixItems` + `items` → `items` + `additionalItems`, boolean subschemas → `{}` / `{"not": {}}`. Annotations draft-04 doesn't have are dropped (e.g. `contentMediaType`, `writeOnly`, `examples`), as are the added keywords and formats outside draft-04, except `uri-reference`, which SDC uses and justinrainbow/json-schema checks. Refused, since draft-04 can't express them: `contains`, `minContains`, `maxContains`, `if`/`then`/`else`, `propertyNames`, `unevaluatedItems`, and `unevaluatedProperties` next to `additionalProperties`, `allOf`, `anyOf`, `oneOf` or `dependentSchemas` (`additionalProperties` wouldn't see their properties).

`php/tests/SdcHeroTest.php` validates every case against the export with justinrainbow/json-schema, the library Drupal core uses for SDC props:

- Default mode: the same verdict as the Portable Schema validators for all cases.
- Core's mode (`ComponentValidator`: `CHECK_MODE_TYPE_CAST`, props the schema doesn't declare dropped first): the same verdict, except for the unknown prop, which core never sees.

## What the cases show

| Instance | Valid | Shows |
|---|---|---|
| [`hero.json`](hero.json) | yes | All props |
| [`hero--heading-only.json`](hero--heading-only.json) | yes | The heading only |
| [`hero--image-extra-key.json`](hero--image-extra-key.json) | yes | Open types: an image may carry extra keys |
| [`hero--missing-heading.json`](hero--missing-heading.json) | no | Missing heading |
| [`hero--missing-alt.json`](hero--missing-alt.json) | no | Image without alt: required at the use site |
| [`hero--empty-alt.json`](hero--empty-alt.json) | no | Image with empty alt |
| [`hero--link-without-uri.json`](hero--link-without-uri.json) | no | Link without uri |
| [`hero--src-with-space.json`](hero--src-with-space.json) | no | Image src with a space |
| [`hero--width-zero.json`](hero--width-zero.json) | no | Image width zero |
| [`hero--unknown-prop.json`](hero--unknown-prop.json) | no | A prop the component doesn't have |

## Gaps

- **Core refuses `hero.component.yml` at discovery:** `ComponentValidator::validateDefinition()` reads `type` of every prop, and these props only have `$ref`.
- **SDC can't resolve `portable:` references.** Core needs the draft-04 export, or an integration that resolves Portable Schema references in `.component.yml` files (Drupal Canvas resolves `json-schema-definitions://` references the same way; core has no such stream wrapper).
- **The export refuses keywords draft-04 doesn't have** (`propertyNames`, `contains`, `if`/`then`/`else`, …) and drops the added keywords. The hero uses none of them.
