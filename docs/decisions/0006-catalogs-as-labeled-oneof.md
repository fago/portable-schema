# 0006: Catalogs as `oneOf` + `const` + `title`, compiled to a set

Status: accepted

## Context
Catalogs restrict a value to the facts a system provides: plugins, bundles, fields, modules. That check runs on almost every validation, so it must be efficient. Catalogs also need labels that can be translated.

JSON Schema has no labeled-enum keyword. JSON Schema form libraries (react-jsonschema-form, JSON Forms, vjsf) converge on `oneOf` (or `anyOf`) with `const` + `title` for labeled options. `enum` with labels in a custom annotation would lose the labels in those tools unless a separate export adds them back, and we want schemas that generic tools can use directly.

## Decision
- **A labeled catalog** is a `oneOf` whose branches each hold a distinct `const` and a `title`, optionally a `description` and `x-portable-*` annotations (e.g. `x-portable-provider`).
- **An unlabeled catalog** is a plain `enum`.
- **`title` and `description` are translatable schema strings,** like everywhere else.
- **Our validators compile a `oneOf` whose branches are all `const`s with distinct values, and every scalar `enum`, to a hash set.** Lookups are O(1); the result is identical, since distinct constants match at most one branch. Values are compared by JSON Schema equality (e.g. `1` equals `1.0`).
- **Errors** are shaped to one `enum` error with the allowed values ([`spec/errors.md`](../../spec/errors.md)); formatters label them with the branches' titles.
- A `oneOf` with non-`const` branches or duplicate constants is evaluated as usual.

## Consequences
- Generic tools render catalogs as selects with labels, with no export step.
- Generic validators stay correct but check catalogs in O(n).
- Catalog size still matters for transfer; very large catalogs may be served filtered by a registry.
