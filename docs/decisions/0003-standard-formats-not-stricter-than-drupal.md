# 0003: Standard formats, not stricter than Drupal's validators

Status: accepted

## Context
JSON Schema defines what a format means by pointing to other standards (RFC 5321 for `email`, RFC 3339 for `date-time`, …), but not how strictly to check it. In 2020-12, `format` is annotation-only by default, and checks may be partial. Validators therefore disagree: PHP's `filter_var`, Drupal's `Email` constraint, browsers and JS libraries accept different edge cases. Drupal's validators differ from the standards in both directions: `Email` rejects quoted local parts, address literals and single-label domains that RFC 5321 allows, and accepts UTF-8 addresses (RFC 6531).

Format names come from the JSON Schema format registry of the next release. OpenAPI has a registry of its own, and validators allow custom formats.

Principle: Portable Schema follows the standard formats without being stricter than Drupal's validators, for compatibility.

## Decision
- **Names:** only formats from the [JSON Schema format registry](https://github.com/json-schema-org/json-schema-spec/blob/main/specs/registries/format.json). No custom formats.
- **Subset:** an allowed subset, listed in [`spec/profile.md`](../../spec/profile.md).
- **Checks:** the profile pins the exact check for each allowed format, so PHP and JS agree. A pinned check follows the standard the registry names for the format: `email` is RFC 5321 `Mailbox`, ASCII only.
- **Where the registry names no grammar,** the pin is not stricter than the systems Portable Schema maps: `decimal` takes Drupal's syntax, without PCRE's trailing newline (a known difference).
- **No variants of a standard format,** neither looser nor stricter ones.
- **A system that is stricter than the standard** runs its exact check on the server as a named check (`portable-check`), e.g. `drupal:Email`.
- **A system that accepts values the standard doesn't** has a known difference, documented by its integration, or normalizes the values ([decision 0004](0004-types-for-all-primitives.md)).
- **Implementations** use these pinned checks, not their base library's built-in format checks.
- **Missing meanings** become types, not formats. If a generic format is missing, we propose it to the registry.

## Consequences
- Generic tools understand our formats, since the names are standard, and the JSON-Schema-Test-Suite's format cases apply unfiltered.
- Our verdict may differ from other validators in edge cases; fixtures document the pinned behaviour.
- `email` accepts quoted local parts, address literals and single-label domains. Drupal rejects them on the server with `drupal:Email`. Widgets that use `<input type="email">` are stricter than the format for the first two.
- UTF-8 addresses are rejected until `idn-email` is pinned.
- Types can build on formats where a bundle of constraints deserves a name, e.g. `email` with a length limit.
