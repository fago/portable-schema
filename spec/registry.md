# Registry

Status: draft.

How schema files on disk get their IDs. The rules for IDs themselves are in [`validation.md`](validation.md#registry).

## Schema IDs

- **Schema IDs are `<namespace>:<path>`,** e.g. `drupal:tool.find_images.input`.
- **The namespace** is a lowercase identifier: `[a-z][a-z0-9_]*`.
- **The path** is segments separated by `/`.
- **`portable:` is reserved** for the type library in [`types/v1/`](../types/v1). Adding a schema with a `portable:` ID, or mounting a directory under `portable`, is refused.

## Mounts

A directory is registered (mounted) under a namespace: `addDirectory($path, $namespace)` in both languages.

- **A file's ID** is `<namespace>:<relative path without .json>`: `catalog/plugins/block.json` mounted as `drupal` → `drupal:catalog/plugins/block`.
- **`$id` in a file is optional.** If present, it must equal the derived ID exactly; otherwise the file is refused. A percent-encoded `$id` (`drupal:block.settings.%2A`) does not equal the decoded ID and is refused.
- **Characters a file name can't hold are percent-encoded,** and decoded in the ID: `drupal:block.settings.*` → `block.settings.%2A.json`. A name that decodes to non-ASCII characters is refused.
- **`*.test.json` files are never registered.**
- **Symbolic links and names starting with `.` are skipped,** files and directories alike.
