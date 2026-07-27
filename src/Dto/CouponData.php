<?php

namespace Jointdots\FiskalizimiKs\Dto;

final class CouponData
{
    /**
     * @param ItemData[]    $items
     * @param PaymentData[] $payments
     */
    public function __construct(
        public readonly array      $items,
        public readonly array      $payments,
        public readonly string     $operatorId,
        public readonly CouponType $type           = CouponType::Sale,
        public readonly ?int       $referenceNo    = null,
        public readonly ?string    $idempotencyKey = null,
        public readonly ?int       $couponId       = null,
        public readonly ?string    $verificationNo = null,
        public readonly ?int       $fiscalTime     = null,
        public readonly int        $totalDiscount  = 0,
        /**
         * What the caller's own record says this coupon totals, in fiscal units.
         * Supplied so the builder can prove the payload it is about to sign agrees
         * with the record it came from; the builder derives both figures itself
         * from the items, so a mismatch means the two have drifted apart. Optional:
         * a caller with no separate record leaves them null.
         */
        public readonly ?int       $expectedTotal    = null,
        public readonly ?int       $expectedTotalTax = null,
    ) {}
}
