# Drupal showcase: node article

The Portable Schema of `node`/`article` content, generated from Drupal's Typed Data API by [`introspect.php`](introspect.php). The article is the one of core's `article_content_type` recipe: `title`, `body` and `field_image` with `alt_field_required: true`, plus the base fields of nodes.

## Generating

In a Drupal 11 site with the recipe applied (generated with Drupal 11.4.10, minimal profile, plus the `restricted_html_format` recipe):

```sh
drush recipe core/recipes/article_content_type
drush recipe core/recipes/restricted_html_format
drush php:script introspect.php -- fixtures/examples/drupal/node-article
```

| File | Contents |
|---|---|
| [`schemas/node.article.json`](schemas/node.article.json) | The bundle: one property per field, each a list of items |
| [`../schemas/field_item.*.json`](../schemas) | One item type per field type, from the field type's default settings. Shared with the other Drupal showcases |
| [`../catalogs/`](../catalogs) | The site's text formats and languages |
| [`node.article.json`](node.article.json), [`node.article.test.json`](node.article.test.json) | The schema under test and its cases |

## Mapping

The data shape is Typed Data's own: field → list of items → properties, e.g. `{"title": [{"value": "…"}]}`.

| Typed Data | Portable Schema |
|---|---|
| Field definition: label, description | `title`, `description` of the list |
| Cardinality, `isRequired()` | `maxItems`; `required` on the entity and `minItems: 1` |
| `isReadOnly()` | `readOnly` |
| Computed fields and properties (`path`, `processed`, `entity`) | Left out: not part of the stored data |
| Item property definitions | `properties` of the item; `isRequired()` → `required` |
| Property data types | Library types per [`docs/drupal.md`](../../../../docs/drupal.md); `filter_format` and a language field's `value` → catalogs |
| `Length`, `Range`, `NotBlank`, `Uuid`, `ComplexData` | `minLength`/`maxLength`, `minimum`/`maximum`, `minLength: 1`, `portable:uuid`, nested `properties` |
| `Count` (on the field list) | `minItems`/`maxItems` |
| `NotNull`, `PrimitiveType` | `required` and the type |
| `AllowedValues` without choices (options providers) | Text formats and languages: their catalogs. Booleans: the type. Entity references: `ValidReference` |
| `ValidReference` (on the field list) | Named check `drupal:ValidReference` on each item's `target_id`, where Drupal reports the violation |
| `FileValidation` | Named check `drupal:FileExtension` with the field's `file_extensions` |
| Other constraints, e.g. `ReferenceAccess` | Named checks `drupal:<Constraint>` with the constraint's options, on the item property, the item or the list |
| A field's own settings (e.g. `max_length`, an image field's `alt_field_required`) | Added next to the `$ref` to the field item type. A field whose settings change a property's type, e.g. string IDs of the `type` field's config entity target, gets its own item schema |

Constraints come from the definitions **and** from the item and list classes (`getConstraints()` on a Typed Data object), since classes like `StringItem` and `EntityReferenceFieldItemList` add `Length` and `ValidReference` at runtime.

## What the cases show

- **Valid:** a complete article; without callbacks, the reference and file checks are listed as unchecked.
- **Invalid:** missing title, empty title field, title too long, image without alt text, unknown text format, an invalid reference and a disallowed file extension (named checks via callbacks, the latter with the check's own message), two images in a single-value field, an unknown field.
- **Partial validation** of one field, as for a form field on blur.

## Gaps

- **`alt_field_required` is not a Typed Data constraint;** only the image widget enforces it. The script derives it from the field settings, so the schema is stricter than Drupal's API validation.
- **Labels:** messages use the property's label (`Text value must be at most 255 characters long.`), where Drupal says `Title: may not be longer than 255 characters.` A rule that uses the field's label for its main property would match Drupal better.
- **Core's article recipe defines `body` as `text_with_summary`,** not `text_long`.
- **Options providers** are code: the script maps the known ones; a generic mapping needs a catalog provider per options provider (see the catalog providers in [`docs/drupal.md`](../../../../docs/drupal.md)).
