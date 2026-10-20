# 0005: Profile-only validators

Status: accepted

## Context
Bowtie runs the official JSON-Schema-Test-Suite against validators of the standard dialects. It doesn't know custom dialects, so it can't test Portable Schema's profile, vocabulary or pinned format checks.

Our validators could be general 2020-12 validators that also support the Portable Schema dialect, or validators for Portable Schema only. Claiming Bowtie compliance would only cover the standard part and could suggest that Portable Schema itself is tested.

## Decision
- **The reference validators validate Portable Schemas only.** Schemas outside the profile are refused when they are loaded, by the dialect's meta-schema.
- **Conformance is defined by our fixtures** plus the official JSON-Schema-Test-Suite **filtered to the profile**.
- **Bowtie is used as an oracle** for choosing and monitoring base libraries (opis, Hyperjump, Corvus), not as a compliance claim for Portable Schema.

## Consequences
- We don't register as a 2020-12 implementation in Bowtie, and don't claim Bowtie results.
- Less work: features outside the profile (e.g. `$dynamicRef`) don't need to work in our validators.
- Full 2020-12 support can be added later, e.g. if a base library is fully compliant anyway. It would be reported separately from Portable Schema conformance.
