# Changelog

All notable public changes are documented in this file. The project follows
Semantic Versioning and the Keep a Changelog format.

## [Unreleased]

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

[Unreleased]: https://github.com/jointdots/fiskalizimi-ks/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/jointdots/fiskalizimi-ks/releases/tag/v0.3.0
