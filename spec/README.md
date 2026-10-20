# Spec

The normative definition of Portable Schema. The spec plus the [fixtures](../fixtures/conformance) define correct behaviour; the implementations follow them.

## Machine-readable

| File | Purpose |
|---|---|
| [`v1/schema.json`](v1/schema.json) | Dialect meta-schema. Schemas declare it with `$schema` |
| [`v1/vocab.json`](v1/vocab.json) | Syntax of the keywords Portable Schema adds |

The `portable-schema.dev` base URI is a placeholder until a domain is chosen.

## Prose

| Document | Covers |
|---|---|
| [`errors.md`](errors.md) | Result format, error codes and params, shaping rules (draft) |
| [`profile.md`](profile.md) | Allowed and restricted standard keywords; rules where JSON Schema leaves a choice. Formats so far (draft) |
| `vocabulary.md` (planned) | Semantics, errors and degradation of each added keyword |
| `normalization.md` (planned) | JSON data model, object vs list, coercion cast table |
| `catalogs.md` (planned) | Catalogs, registry resolution (live and static), site schema dumps |
