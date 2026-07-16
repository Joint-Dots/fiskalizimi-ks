# ATK Requirements Summary

This is a non-authoritative engineering summary. It does not replace current
ATK publications, legislation, certification testing, or written guidance.

| Area | Package assistance | Integrator responsibility |
| --- | --- | --- |
| Coupon payload | Builds POS and citizen protobuf payloads from one snapshot | Confirm the active schema and provide correct business, POS, branch, operator, product, tax, payment, discount, and reference data |
| Signing and QR | Signs payloads with ECDSA P-256 and returns a citizen QR payload | Generate and protect the per-installation key, complete onboarding, print a readable signed QR, and prevent unauthorized key use |
| Submission | Sends signed payloads over HTTPS to the configured ATK endpoint | Obtain valid identifiers and certificates, restrict networking, monitor failures, and validate against the active ATK environment |
| Offline operation | Journals the signed payload and retries retryable failures for up to 48 hours | Keep storage and workers available, visibly mark the receipt `OFFLINE`, alert operators, and ensure submission within the legal deadline |
| Corrections | Requires the original coupon reference for cancel and return coupons | Retain the original coupon, issue a new linked transaction, provide cancellation reasons where required, and prevent destructive edits |
| Receipt | Supplies fiscal identifiers, status, time, transaction number, and QR data | Render every legally required receipt field, language, logo, daily number, payment/tax breakdown, copy marker, cancellation reason, and delivery/printing behavior |
| Fiscal journal | Stores package submission state and signed payload data | Implement immutable retention, encryption at rest, access controls, backups, restore tests, and legally required retention |
| Audit | Provides limited application submission state and opt-in metadata logging | Record protected user, login, sale, cancel, configuration, failure, and technical-event logs and support controlled ATK export |
| Availability and time | Provides queue-compatible retry behavior | Maintain service availability, notify users of failures, synchronize and monitor system time, and operate incident response |
| Certification | Provides reusable integration code and tests | Register the developer and SEF solution, obtain the application ID, complete ATK testing/certification, and notify ATK of relevant changes |

## Fiscal Coupon Identifier (NUIKF)

From *Kerkesat Specifike Teknike dhe Funksionale per Pajisjet Elektronike
Fiskale / Sistemet Fiskale / Softueret Elektronike Fiskale*, point 10,
"Numri unik identifikues i kuponit fiskal (NUIKF)":

- Alphanumeric characters, as a single value, with no divisions, dashes or
  special characters.
- Unique per coupon.
- Maximum length of sixteen (16) characters — a maximum, not an exact length.

The regulation requires the NUIKF to be **unique, not sequential**. The phrase
`ne renditje` describes how the characters are arranged within the one unbroken
value; it does not require a coupon's NUIKF to exceed the previous coupon's,
and the regulation names no ordering key. ATK's own English edition renders the
same phrase as "in the following order:" introducing a component list, and both
ATK protobuf samples document the field only as "a unique value for each
coupon, and it is 16 characters long max".

A random sixteen-character value therefore conforms. The package generates one
when the caller does not supply an application-owned value, and validates
either against `^[A-Z0-9]{1,16}$`.

### Open questions

- **The Albanian and English editions disagree.** ATK's English edition
  specifies a *structured* twenty-eight character NUIKF — fiscalization number
  issued by the TAK Information System, then business unit code, then
  `DDMMYYYYHHMMSS` (worked example: `0123456789012101012025083078`) — and
  states no maximum length. The Albanian edition and both protobuf samples
  state a sixteen character maximum. This package implements the sixteen
  character rule, on the strength of two sources against one and because
  `VerificationNo` is the field actually transmitted. Confirm with ATK before
  certification: if the structured form is current, the NUIKF becomes derived
  configuration rather than a generated value, and this package is wrong.

## Core Operational Expectations

- Every fiscal transaction must have a unique identifier and must not be
  deleted or overwritten.
- Corrections, returns, and cancellations must be new transactions linked to
  the original coupon.
- POS and citizen coupon details used for verification must remain consistent.
- Communication with ATK must use secure HTTPS.
- Coupons created during an outage must be stored securely and resent
  automatically when communication returns.
- A coupon issued before ATK confirmation must visibly state `OFFLINE`.
- Local fiscal and audit data must be protected against unauthorized access
  and alteration.
- The installed POS/SEF must use its own registered key material according to
  current ATK onboarding instructions.
- Operational changes that affect fiscal generation, storage, signing, or
  transmission may require ATK review or notification.

The package covers only part of these expectations. A host application and
approved operational deployment are required for a complete SEF solution.
