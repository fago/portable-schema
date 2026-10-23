# Drupal showcases

Portable Schemas for real Drupal cases, with cases both runners run.

| Showcase | Shows |
|---|---|
| [`config/`](config) | Config entities: core's `block.block.*` and Drupal Canvas' `canvas.component.*`, with dynamic types and plugin catalogs |
| [`node-article/`](node-article) | Content: node/article generated from Typed Data, with field item types and named checks |
| [`sdc-hero/`](sdc-hero) | An SDC component's props with the library's image and link types, exported as draft-04 for core |
| [`agent-tool/`](agent-tool) | Agent tools reusing the image field type: MCP tool definitions and a repair loop, identical in PHP and JS |

Each showcase is self-contained: its own `schemas/` (catalogs under `schemas/catalog/`), mounted as `drupal`, one data file per instance and its expectations next to it. The layout is described in [`../README.md`](../README.md).

How Drupal's config schema types, Typed Data types and constraints map to Portable Schema is described in [`docs/drupal.md`](../../../docs/drupal.md). `drupal:` IDs are placeholders for whatever ID scheme the integration uses.
