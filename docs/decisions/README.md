# Decisions

One file per decision, numbered: `NNNN-short-title.md`. Before the first release, an accepted decision is edited in place; after it, a later decision supersedes it.

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
| [0003](0003-standard-formats-not-stricter-than-drupal.md) | Standard formats from the JSON Schema format registry, pinned to the standard it names, not stricter than Drupal's validators; stricter system checks run on the server |
| [0004](0004-types-for-all-primitives.md) | A named type for every primitive; standard time formats |
| [0005](0005-profile-only-validators.md) | Profile-only validators; conformance by fixtures, Bowtie as oracle |
| [0006](0006-catalogs-as-labeled-oneof.md) | Catalogs as `oneOf` + `const` + `title`, compiled to a set |
