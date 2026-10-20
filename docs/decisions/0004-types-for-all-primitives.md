# 0004: A named type for every primitive

Status: accepted

## Context
Many primitive values can be expressed as a plain JSON Schema type or a registry format (`format: uri`). Defining types only where a format isn't enough would leave two ways to describe the same value, and mappings to systems like Drupal Typed Data would mix types and formats.

## Decision
- **The type library has a named type for every primitive:** `string`, `integer`, `number`, `boolean`, `binary`, `decimal`, `email`, `uri`, `uuid`, `date`, `date-time`, `duration`, `timestamp`, `timespan`, `langcode`, `label`, `text`, `html`, `markdown`, `identifier`, `color`, `weight`, `secret`.
- **Types use formats inside,** e.g. `portable:uri` = `type: string` + `format: uri`.
- **Integrations map their primitives to these types 1:1.**
- **Using a plain type or format without a `$ref` remains valid JSON Schema.** Widget resolution and adapters key on the format and type keywords, so both forms behave the same.
- **Time values use the standard formats.** `date-time` requires a time zone offset (RFC 3339). Systems that store times without an offset add it when converting. Example: Drupal stores datetimes as UTC without an offset, so its integration adds `Z` on export and removes it on import.

## Consequences
- **A uniform, predictable library:** every primitive has one stable reference for adapters, widgets and messages.
- **Some types are thin wrappers** around a format; that's accepted for uniformity.
- **Integrations normalize values to the standard formats**, instead of the library carrying system-specific variants.
