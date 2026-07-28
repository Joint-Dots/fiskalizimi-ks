# Changelog

All notable public changes are documented in this file. The project follows
Semantic Versioning and the Keep a Changelog format.

## [Unreleased]

## [0.8.0] - 2026-07-28

### Fixed

- A coupon discounted by more than half was rejected with "Total discount cannot
  exceed the coupon total." An item's `Total` is what is left *after* its
  markdown, so the coupon total and the discount are disjoint amounts whose sum
  is the pre-discount subtotal. Comparing one against the other was not a loose
  bound but a meaningless one, and it failed exactly the coupons where the money
  taken off is larger than the money left — every markdown over 50%. The bound is
  gone; `TotalDiscount` must still be non-negative.

> Note: the entry below is labelled 0.6.0 but was published as tag `0.7.0`.

## [0.6.0] - 2026-07-27

Contract change. `ItemData::$total` — and `items.*.total` on the REST endpoint —
now carries item units (`EUR 1.00 = 10000`), the same scale as `ItemData::$price`,
instead of cents. Callers must scale their line totals by 100; nothing else about
a coupon changes.

### Changed

- An item's total was sent in cents while its price was sent in ten-thousandths
  of a euro. ATK's verification portal reads both at the item scale, so every
  line rendered a hundredth of its true amount and the portal reported the coupon
  as failing its own arithmetic (`Ka mospërputhje në kalkulim`) — on a coupon ATK
  had otherwise accepted. Item money is now carried in item units throughout.
  ATK's materials disagree on this point: the reference POS samples put the item
  total in cents, and the `pos-golang` readme states both scales in adjacent
  paragraphs. A sandbox coupon submitted on 2026-07-27 reconciled on the portal
  only in item units.
- Coupon-level figures are unchanged and still in cents. `Total`, `TotalTax`,
  `TotalNoTax`, the tax groups, payments, `TotalDiscount`, and both
  `expectedTotal`/`expectedTotalTax` keep their meaning, so a signed coupon's
  totals — and the citizen coupon behind the QR — are byte-identical to 0.5.0 for
  the same sale. Each item is converted before it is summed, so the builder's
  own totals cannot disagree with the ones it validates against.

### Upgrading

Multiply the value passed as `ItemData::$total` (or posted as `items.*.total`) by
100. A caller that keeps line totals in cents changes `total: $grossMinor` to
`total: $grossMinor * 100`. Leave payments, `total`, and `total_discount` alone.
A caller that does not update is rejected before signing: the payment total will
no longer match the item total.

## [0.5.0] - 2026-07-27

Correctness release. No API removals, but see **Upgrading** — consumers that
match exhaustively on `FiscalStatus` must handle one new case.

### Fixed

- Item names were cut to ATK's 120-byte budget with `substr`, which splits a
  UTF-8 sequence whenever the boundary falls inside one. Protobuf then refuses
  the string ("Expect utf-8 encoding") from inside serialization, so the coupon
  could not be signed and failed identically on every retry — the article had to
  be renamed before it could be sold. Albanian names reach this readily: `ë` and
  `ç` are two bytes each. Names are now cut with `mb_strcut`, which respects
  character boundaries while still honouring a byte budget.
- A `2xx` response carrying no parseable transaction number was thrown as
  non-retryable, so the coupon was recorded `rejected` and the operator told ATK
  had permanently refused it. But `2xx` means ATK received the submission and may
  already hold the coupon; recording a rejection over a possible acceptance is
  the one outcome that invites a duplicate. It is now an unknown result: retried
  first, then escalated to `unresolved` for an operator decision.
- Every `4xx` except `408`, `425`, `429` was classified non-retryable, so an
  expired certificate, a stale token, or a mistyped coupon path destroyed valid
  receipts. `401`, `403`, `404`, `405` and `407` refuse the request before the
  coupon is judged and are now treated as device faults: retryable, with a
  message that says so. Verdicts on the payload (`400`, `409`, `422`) stay
  permanent, because resubmitting the same signed bytes earns the same answer.
- The ATK request had no `connect_timeout`, so a black-holed connect consumed
  the entire request budget before the queue learned the device was offline.

### Added

- `FiscalSubmissionException::$unknown` — distinguishes "ATK received this but
  its outcome cannot be read" from a verdict on the coupon.
- `FiscalStatus::Unresolved` and `FiscalCoupon::STATUS_UNRESOLVED` — a coupon
  sent to ATK whose outcome could not be read and did not clear on retry. It is
  held rather than resolved by guessing. The REST API reports it as `409`.
- `CouponData::$expectedTotal` and `$expectedTotalTax` — optional. The builder
  derives both from the items it is handed, so a caller that supplies its own
  figures has them checked before signing: a mismatch means the payload and the
  record it came from have drifted, and a coupon that contradicts its own journal
  entry must not be signed. Tax is carried separately because a caller typically
  reaches it per stored line while the builder reaches it per payload item, so
  the two can disagree while the gross still matches.
- Item names that are already invalid UTF-8 are refused during validation, with
  a `FiscalConfigurationException` naming the problem, rather than failing
  opaquely inside protobuf.

### Changed

- `ext-mbstring` is now required.

### Upgrading

- `FiscalStatus` gained `Unresolved`. A consumer that matches exhaustively over
  it — as the REST controller in this package did — will raise
  `UnhandledMatchError` until the case is handled. Treat it as "needs an operator
  decision": neither issued nor refused, and it will not resolve on its own.
- A submission that previously returned `Rejected` for an unreadable `2xx` or an
  auth/endpoint `4xx` now returns `Queued`. Consumers that treated `Rejected` as
  the terminal signal for those cases should follow the journal instead.

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
