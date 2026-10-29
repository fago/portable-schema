# Decisions

One file per decision, numbered: `NNNN-short-title.md`. A decision is never edited after it is accepted; a later one supersedes it.

```markdown
# NNNN: Title

Status: proposed | accepted | superseded by NNNN

## Context
Why a decision is needed.

## Decision
What we do.

## Consequences
What follows from it, good and bad.
```

| No. | Decision |
|---|---|
| [0001](0001-message-catalog-format.md) | Message catalog: `{name}` placeholders, at most one plural parameter |
| [0002](0002-relative-json-pointer.md) | Relative JSON Pointer for references within data |
| [0003](0003-formats-from-the-registry.md) | Formats from the JSON Schema format registry, with pinned checks |
| [0004](0004-types-for-all-primitives.md) | A named type for every primitive; standard time formats |
| [0005](0005-profile-only-validators.md) | Profile-only validators; conformance by fixtures, Bowtie as oracle |
| [0006](0006-catalogs-as-labeled-oneof.md) | Catalogs as `oneOf` + `const` + `title`, compiled to a set |
