# 0007: Pinned checks follow the standard the registry names

Status: accepted

## Context
[Decision 0003](0003-formats-from-the-registry.md) orients the pinned format checks on browsers and WHATWG where that fits users' expectations, e.g. `email`, and on the referenced RFC otherwise.

The [Drupal compatibility analysis](../drupal-compatibility.md) compared the pinned checks with Drupal's validators and with the standards the format registry names:

- **Neither WHATWG nor RFC 5321 matches Drupal's `email` check.** Drupal rejects quoted local parts, address literals and single-label domains, which RFC 5321 allows, and accepts UTF-8 addresses (RFC 6531). WHATWG is looser than Drupal on other cases: dots in the local part, single-label domains, local parts over 64 characters.
- **WHATWG differs from the standard** on 11 of 37 corpus cases and on 15 of the 65 string cases of the JSON-Schema-Test-Suite, which the profile would have to filter out.

Principle: Portable Schema must not be stricter than Drupal's validators, but it stays with the standard formats.

## Decision
- **Pinned checks follow the standard the format registry names** for the format. `email` is RFC 5321 `Mailbox`, ASCII only.
- **Where the registry names no grammar,** the pin is not stricter than the systems Portable Schema maps. `decimal` takes Drupal's syntax, without PCRE's trailing newline (a known difference).
- **No variants of a standard format,** neither looser nor stricter ones.
- **A system that is stricter than the standard** runs its exact check on the server as a named check (`portable-check`), e.g. `drupal:Email`.
- **A system that accepts values the standard doesn't** has a known difference, documented by its integration, or normalizes the values ([decision 0004](0004-types-for-all-primitives.md)).
- This supersedes 0003's orientation on browsers and WHATWG. The rest of 0003 stays.

## Consequences
- The JSON-Schema-Test-Suite's `email` cases apply unfiltered.
- `email` accepts quoted local parts, address literals and single-label domains. Drupal rejects them on the server with `drupal:Email`.
- Widgets that use `<input type="email">` are stricter than the format for quoted local parts and address literals.
- UTF-8 addresses are rejected until `idn-email` is pinned.
