# ATK Protocol Notes

Reviewed against the public ATK C# repository on June 11, 2026.

## Coupon Types

| Type | Protobuf value | Reference |
| --- | ---: | --- |
| Sale | `1` | `ReferenceNo` omitted or zero |
| Cancel | `2` | `ReferenceNo` is the original sale `CouponId` |
| Return | `3` | `ReferenceNo` is the original sale `CouponId` |

The POS and citizen coupons must use the same coupon type and shared fiscal
snapshot values. Missing `ReferenceNo` on cancel or return coupons is expected
to be rejected.

The ATK PHP sample currently contains the older values `Sale=0`, `Cancel=1`,
and `Return=2`. Those values are not used by this package.

## Monetary Values

- Item prices use integer ten-thousandths of one euro:
  `EUR 1.00 = 10000`.
- Item totals, payments, coupon totals, tax totals, and discounts use integer
  cents: `EUR 1.00 = 100`.
- Public tax codes in the current samples are `A`, `C`, `D`, and `E`.

Tax and rounding calculations must be validated against current official ATK
examples. Sample repository numbers are not a complete calculation standard.

## Submission

The public sample submits a JSON body to `POST /pos/coupon`:

```json
{
  "details": "base64 protobuf POS coupon",
  "signature": "base64 ECDSA signature"
}
```

A successful public-sample response includes `transaction_id`. Error response
shape and retryability must be confirmed against the active environment.

## Signing and Citizen QR

The current public flow:

1. Serialize the protobuf payload.
2. Base64-encode the protobuf bytes.
3. Hash the Base64 text with SHA-256.
4. Sign the hash using the installed ECDSA P-256 private key.
5. Base64-encode the signature.

The citizen QR data is:

```text
base64(CitizenCoupon protobuf)|base64(signature)
```

Each installed POS/SEF is expected to have distinct registered identifiers and
key material. Do not move or share private keys without current written ATK
approval for that deployment topology.
