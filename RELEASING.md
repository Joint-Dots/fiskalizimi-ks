# Releasing

This checklist applies to public package releases.

## Package Verification

- Confirm `README.md`, `DISCLAIMER.md`, `CHANGELOG.md`, `SECURITY.md`, and the
  ATK reference notes match the release.
- Remove private keys, certificates, tokens, taxpayer data, signatures, and
  real coupon payloads from the repository and Git history.
- Run `composer validate --strict --no-check-lock`.
- Run `composer test`.
- Run the complete GitHub Actions PHP/Laravel matrix.
- Run PHP syntax checks and `git diff --check`.
- Confirm generated protobuf classes match `proto/models.proto`.
- Compare `proto/models.proto` with the current official ATK contract.
- Inspect the Composer archive and verify internal files are absent.
- Verify `composer require jointdots/fiskalizimi-ks` in a clean Laravel
  application.

## Release

- Update `CHANGELOG.md` with the release date and version.
- Create an annotated SemVer tag from a passing `main` commit.
- Publish a GitHub release using the matching changelog section.
- Confirm Packagist receives the tag and reports the expected dependencies.

## ATK Boundary

Do not describe a release as ATK-certified, compliant, approved, or
production-ready based only on package tests.

Before making a deployment-specific compliance claim:

- verify the active protobuf schema, enum values, endpoint, and error contract;
- run successful ATK test-environment transactions;
- validate official tax and rounding examples;
- validate sale, mixed payment, discount, return, cancel, offline, and
  duplicate-request scenarios;
- review every required receipt field and the literal `OFFLINE` marker;
- verify encrypted storage, immutable audit history, event logs, export,
  retention, time synchronization, monitoring, and recovery;
- verify a unique certificate and private key for each installed POS/SEF; and
- complete the applicable ATK registration and certification process.
