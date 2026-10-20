# Drupal showcase: SDC hero

The props of a single-directory component, described with the library's shared types: `portable:label`, `portable:html`, `portable:image` and `portable:link`. Drupal core validates SDC props with JSON Schema draft-04, so the showcase also exports them as draft-04.

| File | Contents |
|---|---|
| [`hero/hero.component.yml`](hero/hero.component.yml) | The component's metadata; its `props` reference the shared types |
| [`schemas/sdc.hero.json`](schemas/sdc.hero.json) | The same props as a registry schema. A test checks that both match |
| [`hero.draft-04.json`](hero.draft-04.json) | The draft-04 export from `Exporter::draft04()` / `Exporter.draft04()`, identical in PHP and JS |
| [`hero.json`](hero.json), [`hero.test.json`](hero.test.json) | The schema under test and its cases |

## Mapping

| Prop | Portable Schema |
|---|---|
| `heading` (required) | `portable:label` + `minLength: 1` |
| `text` | `portable:html` |
| `image` | `portable:image`; the use site requires `alt` and makes it non-empty |
| `cta` | `portable:link` |

`image` and `link` are open object types: `src` / `uri` are required, other keys are allowed, and use sites add constraints.

## Draft-04 export

The export inlines the referenced types, merges the use-site constraints and converts 2020-12 keywords: `unevaluatedProperties` → `additionalProperties`, `const` → `enum`. Formats outside draft-04 are dropped, except `uri-reference`, which SDC uses and justinrainbow/json-schema checks.

`php/tests/SdcHeroTest.php` validates every case of `hero.test.json` against the export with justinrainbow/json-schema, the validator Drupal core uses for SDC props: it gives the same verdict as the Portable Schema validators for all of them.

## What the cases show

- **Valid:** all props, the heading only, an image with an extra key (open type).
- **Invalid:** missing heading, image without alt (required at the use site), empty alt, link without `uri`, an image `src` that isn't a URI reference, image width zero, an unknown prop.

## Gaps

- **SDC can't resolve `portable:` references.** Core needs the draft-04 export, or an integration that resolves Portable Schema references in `.component.yml` files (core already resolves `json-schema-definitions://` references).
- **The export loses keywords draft-04 doesn't have:** `propertyNames`, `contains`, `if`/`then`/`else` and the added keywords. The hero uses none of them.
