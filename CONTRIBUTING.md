# Contributing

## Development Setup

```bash
composer install
composer test
```

Use PHP 8.2 or later with OpenSSL, mbstring, and SQLite extensions enabled.

## Pull Requests

Keep changes focused and include tests. Fiscal protocol changes must include:

- The ATK source and publication date
- The affected protobuf fields or API behavior
- A regression test
- Any migration or compatibility impact
- An update to `references/atk/requirements.md` when the public requirements
  mapping changes

Do not include real taxpayer data, keys, certificates, API tokens, signatures,
or coupon payloads in tests, issues, logs, or pull requests.

Generated files under `src/Generated` must be regenerated from
`proto/models.proto`; do not edit them manually.

## Compatibility

Public DTO constructor changes must remain backward compatible until the next
major version. New optional parameters should be appended to constructors.

## Reporting Bugs

Use a GitHub issue for ordinary bugs. Use the private process in
`SECURITY.md` for vulnerabilities or anything involving credentials or
production fiscal data.
