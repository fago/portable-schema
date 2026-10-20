# 0002: Relative JSON Pointer for references within data

Status: accepted

## Context
Several keywords refer to other values in the same instance: `source` in `portable-typeBy` and `portable-refine`, `pointer` in `portable-compare`. They must work at any depth, because one type is reused in different places, so they need to go "up" from the evaluated location.

Options considered:
- **JSONPath (RFC 9535):** absolute only, no parent navigation.
- **Path style (`../plugin`):** readable, but not a standard.
- **Dotted paths** (Drupal `%parent.plugin`): ambiguous for keys with dots, not standardized.
- **Library-specific syntaxes** (Formily `..plugin`, JMESPath).

## Decision
- **Relative JSON Pointer** ([draft-hha-relative-json-pointer](https://datatracker.ietf.org/doc/html/draft-hha-relative-json-pointer)), limited to its core form: a non-negative integer (levels up), followed by a JSON Pointer or `#` (key name).
- **Index adjustments** (`1+1`) are not allowed.
- **Locations in results** stay absolute JSON Pointers (RFC 6901).

## Consequences
- **Already implemented by both candidate base libraries:** Ajv (`$data`) and opis (`JsonPointer::isRelativePointer()`). JSON Hyper-Schema uses it in `templatePointers`.
- **The draft stalled in 2023.** We pin the subset we use; `spec/v1/vocab.json` enforces it with a pattern.
- **Less readable than path syntax** (`1/plugin`). Docs and the config schema mapping table (`[%parent.plugin]` → `1/plugin`) cover that.
