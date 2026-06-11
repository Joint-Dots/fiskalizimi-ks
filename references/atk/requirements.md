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
