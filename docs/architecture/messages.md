# Messages

How our implementations turn the structured errors of [`spec/errors.md`](../../spec/errors.md) into human, localized messages. The catalog format follows [decision 0001](../decisions/0001-message-catalog-format.md).

## Pipeline

```
validate ──► result (code, params, locations) + annotations
         ──► formatter ──► message requests {id, source, params, count}
         ──► translator (host adapter) ──► text
```

The validator never produces text. The formatter never translates; translation is the host's job.

## Formatter

Input: one error, the annotations collected at its location, and the catalog ([`messages/en.json`](../../messages/en.json)).

Output, per error:

```json
{ "id": "minLength", "source": "{label} must be at least {limit} characters long.", "params": { "label": "Machine name", "limit": 3 }, "count": 3 }
```

- **`label`:** the `title` at the error's location; otherwise the property name. For `required` and `dependentRequired`, it's the title of the missing property's schema.
- **Derived labels:** `otherLabel` (`compare.*`) and `dependencyLabel` (`dependentRequired`) are titles looked up the same way.
- **Lists** (`allowed`): the `title`s of the allowed `oneOf` entries if present, otherwise the values, joined with `, `.
- **Plurals:** `count` is the value of the message's `plural` parameter. `source` is the `one` or `other` form for English.
- **Custom messages:** `x-portable-messages` at the location overrides `source` for its code. The `id` stays the same.
- **Schema strings** (`label`, custom messages) are passed through as they are. Hosts deliver schemas already localized, or translate them with the same translator.

## Translator interface

Same shape in both languages:

```php
interface Translator {
  public function translate(string $id, string $source, array $params, ?int $count): string;
}
```

```ts
interface Translator {
  translate(id: string, source: string, params: Record<string, unknown>, count?: number): string
}
```

`count` is set only when the message has a plural parameter. The adapter then also receives both forms through the catalog it was generated from.

## Adapters

| | Standalone (in the core packages) | Drupal `t()` (Drupal integration) | vue-i18n (`portable-schema/vue-i18n`, optional) |
|---|---|---|---|
| Key | `id` | English `source` text, context `portable_schema` | `portableSchema.<id>` |
| Placeholders | `{name}` replaced with the plain value | `{name}` → `@name`, so values are escaped | `{name}`, native |
| Plurals | `one`/`other` via `Intl.PluralRules` or PHP `intl` | `formatPlural($count, one, other)` with the plural parameter as `@count` | `"one \| other"`, passed `count` |
| Translations | Locale catalogs `messages/<langcode>.json`, if present | localize.drupal.org / interface translation; plural formulas from Drupal | App locale files; plural rules from vue-i18n `pluralRules` |
| Generated artefact | – | A PHP class with literal `t()` / `formatPlural()` calls, so potx extracts the strings; a `.pot` file | Locale message objects, with `@`, `\|`, `{` and `}` in literal text escaped |

The core packages contain only the interface and the standalone translator. The Drupal adapter lives in the Drupal integration; the vue-i18n adapter is an optional subpath export with vue-i18n as an optional peer dependency.

## Catalog rules

A linter checks every catalog entry:

- **Shape:** either `text`, or `plural` + `one` + `other`.
- **Placeholders:** only `{name}`. Every placeholder is a param of the code or a derived label.
- **Plurals:** the `plural` param is numeric.
- **Content:** no HTML, no ICU syntax.

## Escaping

- **Params are plain text.** Hosts escape them: `@` placeholders in Drupal, text interpolation in Vue.
- **Never render messages as HTML.**

## Fixtures

- Message fixtures compare rendered English text from the standalone translator, so both implementations produce identical strings.
- Other locales are not compared, since ICU/CLDR versions in PHP `intl` and JS `Intl` can differ.
