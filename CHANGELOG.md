# Changelog

All notable public changes are documented in this file. The project follows
Semantic Versioning and the Keep a Changelog format.

## [Unreleased]

## [0.4.0] - 2026-07-18

Conformance release. No breaking changes.

### Changed

- NUIKF validation widened from `^[A-F0-9]{16}$` to `^[A-Z0-9]{1,16}$`. The
  regulation requires the NUIKF to be alphanumeric with a maximum length of 16
  characters (point 10, "Numri unik identifikues i kuponit fiskal"). Hex is a
  strict subset of the alphanumeric set, so the old pattern rejected conformant
  application-supplied values containing `G`-`Z`, and 16 is a maximum rather
  than an exact length. Generated values are unchanged, so callers relying on
  generation see no difference.
- The pattern and its message now live in one place, `Engine\VerificationNo`,
  instead of being duplicated in `CouponSnapshot` and `CouponBuilder`.
- `CouponBuilder` now stamps the fields shared by the POS and citizen payloads
  from a single call site, so the two encodings cannot diverge by construction.

### Added

- `verification_no` — an optional field on the HTTP API and `CouponData`, for
  applications that own a NUIKF counter. Omit it and one is generated.
- Test coverage for the HTTP API, which previously had none.

### Fixed

- A NUIKF with a trailing newline passed validation. PHP's `$` also matches
  before a trailing newline, so `"0000000000000001\n"` — 17 characters — was
  accepted and reached the signed payload and the 16-character journal column.
  The pattern is now anchored with the `D` modifier.
- NUIKF generation retried collisions in an unbounded loop, which would hang a
  request forever against a misbehaving uniqueness check. It now gives up after
  five attempts and raises `FiscalConfigurationException`.

### Notes

- An earlier draft of this release removed the NUIKF generator, on the reading
  that the regulation required the value to be *ordered* and that ordering could
  only come from an application-owned counter. That reading was wrong: the
  regulation requires the NUIKF to be **unique**, not sequential, and the
  package's random 16-character value already conformed. The NUIKF rule is now
  recorded with its source in `references/atk/requirements.md`, along with an
  unresolved disagreement between ATK's Albanian and English editions.

## [0.3.0] - 2026-06-11

Initial public integration-preview release.

### Added

- Laravel package for building, signing, journaling, and submitting ATK
  electronic fiscal coupons
- POS and citizen protobuf generation from one immutable snapshot
- ECDSA P-256 signing and citizen QR payload generation
- Sale, cancel, and return support with current ATK coupon-type wire values
- Multi-tender payments and total-discount support
- Idempotent fiscal journal and fixed 48-hour retry window
- Optional bearer-token REST API
- Laravel 11, 12, and 13 compatibility matrix
- Public ATK source notes, requirements mapping, disclaimer, and release policy

### Security

- Request logging is opt-in and metadata-only
- Private keys, signatures, QR payloads, and coupon lines are excluded from
  package request logs

[Unreleased]: https://github.com/jointdots/fiskalizimi-ks/compare/v0.4.0...HEAD
[0.4.0]: https://github.com/jointdots/fiskalizimi-ks/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/jointdots/fiskalizimi-ks/releases/tag/v0.3.0
