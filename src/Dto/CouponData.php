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
    ) {}
}
