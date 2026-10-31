# 0007: Pinned checks follow the standard the registry names

Status: accepted

## Context
The format registry names a standard for each format, e.g. RFC 5321 for `email`, and the JSON-Schema-Test-Suite tests against it. Drupal's validators differ from these standards in both directions: `Email` rejects quoted local parts, address literals and single-label domains that RFC 5321 allows, and accepts UTF-8 addresses (RFC 6531). Browsers (`<input type="email">`) differ again. A pinned check that follows none of them exactly needs fixtures of its own and can't reuse the test suite.

Principle: Portable Schema follows the standard formats without being stricter than Drupal's validators, for compatibility.

## Decision
- **Pinned checks follow the standard the format registry names** for the format. `email` is RFC 5321 `Mailbox`, ASCII only.
- **Where the registry names no grammar,** the pin is not stricter than the systems Portable Schema maps. `decimal` takes Drupal's syntax, without PCRE's trailing newline (a known difference).
- **No variants of a standard format,** neither looser nor stricter ones.
- **A system that is stricter than the standard** runs its exact check on the server as a named check (`portable-check`), e.g. `drupal:Email`.
- **A system that accepts values the standard doesn't** has a known difference, documented by its integration, or normalizes the values ([decision 0004](0004-types-for-all-primitives.md)).

## Consequences
- The JSON-Schema-Test-Suite's `email` cases apply unfiltered.
- `email` accepts quoted local parts, address literals and single-label domains. Drupal rejects them on the server with `drupal:Email`.
- Widgets that use `<input type="email">` are stricter than the format for quoted local parts and address literals.
- UTF-8 addresses are rejected until `idn-email` is pinned.
