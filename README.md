# Fiskalizimi KS for Laravel

Laravel integration for building, signing, journaling, and submitting Kosovo
ATK electronic fiscal coupons.

> [!WARNING]
> This is an unofficial helper package. It is not affiliated with, endorsed
> by, or certified by ATK. It is not an ATK certificate, a complete SEF
> implementation, legal advice, or a guarantee of compliance. The integrator
> remains responsible for checking current official requirements, completing
> the implementation, obtaining certification, and operating it correctly.

Read [DISCLAIMER.md](DISCLAIMER.md) and the
[ATK reference notes](references/atk/README.md) before relying on this package.
Official ATK publications and written instructions always take precedence.

## Status

The package currently provides:

- Protobuf POS and citizen coupon generation from one immutable snapshot
- ECDSA P-256 signing
- ATK submission through `POST /pos/coupon`
- Multi-tender payments
- Sale, return, and cancellation coupons
- A signed citizen QR payload, including during ATK outages
- A database-backed fiscal journal
- Idempotency keys
- Automatic retry for retryable failures through a fixed 48-hour window
- Unknown ATK results held for an operator decision rather than guessed
- Optional verification that the payload agrees with the caller's own record
- An optional bearer-token REST API

The host application remains responsible for:

- Rendering every legally required receipt field and the RKS fiscal logo
- Marking receipts issued before ATK confirmation with the text `OFFLINE`
- Printing or electronically delivering the receipt
- Product, tax, payment, and discount mapping
- Daily receipt numbering
- Official-language support
- Synchronizing fiscal time with the ATK/system timezone
- Encrypting protected local fiscal data and enforcing immutable audit history
- Recording and exporting user, configuration, failure, and technical-event logs
- Secure per-POS key generation, custody, backup policy, and access controls
- ATK registration, certification, onboarding, and operational procedures

See the public [ATK requirements summary](references/atk/requirements.md)
before production use.

## Requirements

- PHP 8.2 or later
- Laravel 12 or 13
- OpenSSL PHP extension
- Mbstring PHP extension
- A database supported by Laravel
- A queue worker when automatic offline retry is enabled
- An ATK-issued application ID and installation identifiers
- An ECDSA P-256 private key associated with the installed SEF/POS

## Installation

```bash
composer require jointdots/fiskalizimi-ks
```

Publish the configuration and migration:

```bash
php artisan vendor:publish --tag=fiskalizimi-config
php artisan vendor:publish --tag=fiskalizimi-migrations
php artisan migrate
```

The migration intentionally does not drop fiscal audit columns on rollback.

## Configuration

```dotenv
FISCAL_TABLE=kuponat_fiskal

FISCAL_ATK_BASE_URL=https://fiskalizimi.atk-ks.org
FISCAL_ATK_COUPON_PATH=/pos/coupon
FISCAL_ATK_TIMEOUT=10

FISCAL_BUSINESS_ID=
FISCAL_APPLICATION_ID=
FISCAL_POS_ID=
FISCAL_BRANCH_ID=
FISCAL_LOCATION=
FISCAL_KEY_PATH=/absolute/path/private-key.pem
FISCAL_KEY_PASSPHRASE=

FISCAL_RETRY_AUTO_DISPATCH=true

FISCAL_API_ENABLED=false
FISCAL_API_PREFIX=api/fiscal
FISCAL_API_TOKEN=
FISCAL_API_LOG_REQUESTS=false
```

Use `https://fiskalizimi-test.atk-ks.org` during ATK testing.

Relative key paths are resolved below Laravel's `storage/app` directory.
Production keys should use an absolute path outside the web root, restrictive
filesystem permissions, and no source-control or container-image inclusion.

ATK guidance requires a distinct key for each installed POS/SEF. Do not use a
shared central key or move a private key to a managed VPS unless ATK has
approved that exact deployment model in writing.

## Programmatic Usage

```php
use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;
use Jointdots\FiskalizimiKs\Facades\Fiscalizer;

$coupon = new CouponData(
    items: [
        new ItemData(
            name: 'Product A',
            price: 100000, // EUR 10.0000
            unit: 'cope',
            quantity: 1.0,
            total: 100000, // EUR 10.0000
            taxRate: 'E',
        ),
    ],
    payments: [
        new PaymentData(PaymentType::Cash, 1000),
    ],
    operatorId: 'cashier-12',
    idempotencyKey: 'order-2026-000123',
    totalDiscount: 0,
);

$result = Fiscalizer::fiscalize(
    $coupon,
    app(\Jointdots\FiskalizimiKs\Dto\FiscalConfig::class),
);
```

For returns and cancellations, set `CouponData::type` and provide the original
coupon ID in `referenceNo`. A sale must not include a reference number.

### Fiscal Coupon Identifier (NUIKF)

The package generates a conformant NUIKF for each coupon — a unique, random,
16-character alphanumeric value — so `verificationNo` can be omitted. Supply it
only if your application owns the identifier already; supplied values are
validated against `^[A-Z0-9]{1,16}$` and rejected if they collide with a
previously issued coupon.

See `references/atk/requirements.md` for the rule and its source, including an
unresolved disagreement between ATK's Albanian and English editions of the
technical requirements.

### Monetary Units

- Item `price` and item `total`: integer ten-thousandths of one euro
  (`EUR 1.00 = 10000`)
- Coupon total, payments, tax, and discount values: integer cents
  (`EUR 1.00 = 100`)
- Tax codes: `A` exempt, `C` 0%, `D` 8%, `E` 18%

An item carries its money at the item scale and the coupon carries its own in
cents, so the same amount appears twice at two scales. The package converts each
item before summing it, so a caller supplies item figures at the item scale and
everything else in cents.

ATK's own materials disagree about the item total — the reference POS samples
put it in cents — but the verification portal reads it at the item scale, and a
coupon whose item rows are sent in cents is reported there as failing its own
arithmetic even after ATK accepts it. See `references/atk/requirements.md`.

The package checks that payment totals equal item totals. The caller must
calculate line totals and discounts according to the current ATK rules.

## REST API

Set `FISCAL_API_ENABLED=true` and configure a strong
`FISCAL_API_TOKEN`. The following routes are registered:

```text
POST /api/fiscal/coupons
POST /api/fiscal/coupons/return
POST /api/fiscal/coupons/cancel
GET  /api/fiscal/coupons/{id}
```

Example sale:

```json
{
  "idempotency_key": "pos-order-000123",
  "operator_id": "cashier-12",
  "items": [
    {
      "name": "Product A",
      "price": 100000,
      "unit": "cope",
      "quantity": 1,
      "total": 100000,
      "tax_rate": "E",
      "type": "TT"
    }
  ],
  "payments": [
    {
      "type": "cash",
      "amount": 1000
    }
  ],
  "total": 1000,
  "total_discount": 0
}
```

`verification_no` is optional and may be added to supply an application-owned
NUIKF; omit it and the package generates one.

Send `Authorization: Bearer <token>`. Expected result statuses are:

- `200`: fiscalized by ATK
- `202`: queued or still submitting
- `409`: held for an operator decision — sent to ATK, but its answer could not
  be read and retrying did not clear it. Will not resolve on its own.
- `422`: permanently rejected by ATK
- `500`: local build, configuration, or signing failure

Request logging is disabled by default. When enabled, only request metadata is
logged; coupon lines, signatures, and QR payloads are excluded.

## Offline Operation

The signed QR and journal data are created before the ATK request. The same
signed payload is queued for resubmission on retryable transport failures and on
HTTP `408`, `425`, `429`, or `5xx`.

Responses that refuse the request without judging the coupon — `401`, `403`,
`404`, `405`, `407` — are treated as device or configuration faults rather than
verdicts, and also queue. An expired certificate or a mistyped coupon path must
not destroy a valid receipt; delivery resumes once the device is corrected.

A `2xx` whose body carries no transaction number is an **unknown result**: ATK
received the submission and may already hold the coupon. It is never recorded as
a rejection. The coupon is retried — an intercepting proxy or captive portal
answering `200` clears on its own — and only if retrying does not resolve it does
the coupon move to `unresolved`, where it waits for an operator decision instead
of the package guessing an outcome in either direction.

Run a queue worker:

```bash
php artisan queue:work
```

The retry job keeps a fixed deadline 48 hours after it is created. Monitor
queued and failed records. A queue worker outage does not satisfy the legal
requirement to submit offline coupons within 48 hours.

When `status` is `queued`, the host receipt must still include the signed QR
and must visibly identify the receipt as `OFFLINE`.

For applications with multiple businesses or dynamically resolved POS
configurations, disable the built-in automatic dispatch and provide a
tenant-aware retry implementation. The built-in job resolves one
`FiscalConfig` from the Laravel container.

## Security

- Never commit private keys, certificates, API tokens, or real coupon payloads.
- Expose the optional API only through HTTPS and network access controls.
- Rotate the API token separately from the ATK signing key.
- Keep `APP_DEBUG=false` in production.
- Restrict access to the fiscal journal and application logs.
- Review [SECURITY.md](SECURITY.md) before reporting a vulnerability.

## ATK Verification

The live integration test is skipped unless test credentials are provided:

```bash
ATK_TEST_KEY_PATH=/path/to/test-key.pem \
ATK_TEST_BUSINESS_ID=1001 \
ATK_TEST_APPLICATION_ID=42 \
ATK_TEST_POS_ID=1 \
ATK_TEST_BRANCH_ID=1 \
ATK_TEST_LOCATION="Test Location" \
ATK_TEST_BASE_URL=https://fiskalizimi-test.atk-ks.org \
composer test
```

Do not infer ATK acceptance from unit tests. Record the successful test
transaction and the exact package commit used for certification.

### Coupon type contract

The current ATK C# sample and protobuf contract use these wire values:

| Coupon type | Wire value | `ReferenceNo` |
| --- | ---: | --- |
| Sale | `1` | Must be omitted/zero |
| Cancel | `2` | Original sale `CouponId` |
| Return | `3` | Original sale `CouponId` |

The POS and citizen coupons must use the same type. `ReferenceNo` exists on
the POS coupon and is mandatory for cancel and return coupons.

The C# repository was updated on June 11, 2026 to correct its documented
cancel/return values. The PHP repository still contains the older
`Sale=0`, `Cancel=1`, `Return=2` schema and must not be used as the source of
truth for enum wire values.

Primary technical references:

- [ATK C# POS sample](https://github.com/fiskalizimi/pos-csharp) - current
  public compatibility baseline
- [ATK PHP POS sample](https://github.com/fiskalizimi/pos-php) - historical;
  its checked-in protobuf schema is stale
- [ATK Go POS sample](https://github.com/fiskalizimi/pos-golang)
- [ATK test Swagger](https://fiskalizimi-test.atk-ks.org/swagger/index.html)

See [references/atk](references/atk/README.md) for source precedence,
document fingerprints, and the package/host responsibility mapping.

## Development

```bash
composer install
composer test
```

The checked-in classes under `src/Generated` are generated from
`proto/models.proto`. When the ATK schema changes, regenerate them with the
same `protoc` version, review the wire-format diff, and run the live test
environment suite before release.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Fiscal protocol changes must cite an
ATK source and include regression coverage.

## License

MIT. See [LICENSE](LICENSE).
