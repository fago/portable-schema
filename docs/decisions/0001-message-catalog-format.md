# 0001: Message catalog format

Status: accepted

## Context
Validation errors need human, localized messages. The main hosts translate differently:

- Drupal `t()`: the English source text is the key; placeholders `@name`; plurals via `formatPlural()`; translations from localize.drupal.org.
- vue-i18n: message IDs as keys; placeholders `{name}`; plurals via `one | other`.

ICU MessageFormat fits neither without custom compilers.

## Decision
- The catalog holds English source texts keyed by message ID.
- Placeholders are `{name}` only.
- A message has at most one plural parameter, with `one` and `other` forms in the source.
- No select syntax, no nesting, no HTML.
- Translation happens in the host system through adapters generated from the catalog. The formatter itself does not translate.

## Consequences
- Both adapters are mechanical conversions; strings reach localize.drupal.org and vue-i18n locale files unchanged in meaning.
- Plural forms of other languages come from each host's plural rules.
- Messages that would need select or several plural parameters must be split into separate messages.
