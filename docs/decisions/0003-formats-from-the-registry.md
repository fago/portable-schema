# 0003: Formats from the JSON Schema format registry

Status: accepted; the orientation on browsers and WHATWG is superseded by [decision 0007](0007-pinned-checks-follow-the-standard.md)

## Context
JSON Schema defines what a format means by pointing to other standards (RFC 5321 for `email`, RFC 3339 for `date-time`, …), but not how strictly to check it. In 2020-12, `format` is annotation-only by default, and checks may be partial. Validators therefore disagree: PHP's `filter_var`, Drupal's `Email` constraint and JS libraries accept different edge cases.

Format names come from the JSON Schema format registry of the next release. OpenAPI has a registry of its own, and validators allow custom formats.

## Decision
- **Names:** only formats from the [JSON Schema format registry](https://github.com/json-schema-org/json-schema-spec/blob/main/specs/registries/format.json). No custom formats.
- **Subset:** an allowed subset, listed in [`spec/profile.md`](../../spec/profile.md).
- **Checks:** the profile pins the exact check for each allowed format, so PHP and JS agree.
  - **Pragmatic choice per format:** what we can support best and identically in both languages.
  - **Orientation:** the standard the registry names ([decision 0007](0007-pinned-checks-follow-the-standard.md)).
- **Implementations** use these pinned checks, not their base library's built-in format checks.
- **Missing meanings** become types, not formats. If a generic format is missing, we propose it to the registry.

## Consequences
- Generic tools understand our formats, since the names are standard.
- Our verdict may differ from other validators in edge cases; fixtures document the pinned behaviour.
- Types can build on formats where a bundle of constraints deserves a name, e.g. `email` with a length limit.
