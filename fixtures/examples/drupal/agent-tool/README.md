# Drupal showcase: agent tools

Two tools of the photo album in [Dries Buytaert's post](https://dri.es/one-description-inside-and-outside-drupal), described once as Portable Schemas: `find_images` (search by words in title, caption or alt text) and `update_image_alt`. Their schemas reuse the image field's item type of the [node article showcase](../node-article), so the alt text has the same rules, labels and messages in the content model and in the tools.

| File | Contents |
|---|---|
| [`schemas/tool.*.json`](schemas) | Input and output schemas of both tools, and the image they return |
| `*.input.json`, `*.output.json` + `.test.json` | Cases for the tool inputs and outputs, run by both runners |
| [`repair-loop.php`](repair-loop.php), [`repair-loop.mts`](repair-loop.mts) | The same script in PHP and JS: the MCP tool definitions, then calls with invalid arguments, the errors returned, and the corrected calls |
| [`repair-loop.txt`](repair-loop.txt) | Their output, identical in both languages |

```sh
php repair-loop.php
npx tsx repair-loop.mts
```

`scripts/compare-results.sh` checks that both print `repair-loop.txt`.

## Mapping

| Tool API (`#[ToolMethod]`, `#[ToolProperty]`) | Portable Schema |
|---|---|
| Input from the method signature and docblock: `query` (string), "Words to look for in the title, caption or alt text." | `tool.find_images.input`: `query` as `portable:label` with that `description` |
| `#[ToolProperty(description: …, media_type: 'text/plain')]` on the image's alt text getter | `alt` references the image field item's `alt` (`drupal:field_item.image#/properties/alt`); the use site adds the description and `maxLength` |
| `media_type`: plain text, HTML, URI | `portable:label`, `portable:html`, `portable:uri` |
| Image IDs that must exist | `portable-check: {"drupal:ValidReference": {"target_type": "file"}}` |
| Generated JSON Schema for MCP | `Exporter::inline()` / `Exporter.inline()`: references merged where that keeps the meaning, else `allOf`; named checks and `portable:` descriptions dropped |

## What it shows

- **One description inside and outside Drupal:** the image's alt text rules come from the field type, not from the tool.
- **Standard-only export:** `inputSchema` and `outputSchema` are plain JSON Schema 2020-12 that MCP clients and LLM providers accept. Named checks are not in it; they run in the tool implementation.
- **Repair loop:** an invalid call returns an MCP tool error with one message per problem as text and the structured errors (location, code, params, message), so the agent can fix exactly the wrong arguments. A check on a value with the wrong type is not reported, so the agent sees the type error alone.
- **Cases:** valid and invalid inputs (empty query, limit too high, an unknown argument, a missing query, empty alt text, a string ID, a missing image) and outputs.

## Gaps

- **Export profiles per provider** (e.g. OpenAI's strict mode: every property in `required`) are not built; the export is plain 2020-12.
- **No "did you mean" suggestions** yet (roadmap).
