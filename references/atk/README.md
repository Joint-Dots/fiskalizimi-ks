# ATK Reference Notes

These notes summarize public ATK material reviewed for this package. They are
provided for engineering orientation only and are not an official
translation, legal interpretation, complete requirements specification, or
certification statement.

Reviewed: June 11, 2026.

## Source Precedence

When sources differ, use this order:

1. Current legislation, official ATK publications, and written ATK
   instructions
2. The active ATK test environment and its Swagger/API behavior
3. The current ATK C# POS sample and protobuf schema
4. Other ATK sample repositories
5. Summaries in this repository

The C# repository is treated as the current public protocol baseline because
it contains the newer protobuf contract and received coupon-type
documentation corrections on June 11, 2026. The public PHP sample still
contains an older incompatible schema.

## Files

- [requirements.md](requirements.md): package and integrator responsibility
  mapping
- [protocol.md](protocol.md): public protobuf and HTTP compatibility notes
- [sources.md](sources.md): source links, retrieval information, and document
  hashes

The source PDFs are not redistributed in this repository. Obtain current
copies from ATK and verify their revision before implementation.
