# Architecture

How the reference implementations in [`php/`](../../php) and [`js/`](../../js) are built. Both follow the same design, so a change can be reviewed side by side.

| Document | Covers |
|---|---|
| [`messages.md`](messages.md) | Formatting errors into messages; translator adapters for Drupal `t()` and vue-i18n |
| `validator.md` (planned) | Compile and interpret steps, keyword plugin API |
| `registry.md` (planned) | Schema registry, catalog resolution (live and static), providers |
