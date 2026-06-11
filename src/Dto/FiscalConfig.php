<?php

namespace Jointdots\FiskalizimiKs\Dto;

final class FiscalConfig
{
    public function __construct(
        public readonly int     $businessId,
        public readonly int     $applicationId,
        public readonly int     $posId,
        public readonly int     $branchId,
        public readonly string  $location,
        public readonly string  $privateKeyPath,
        public readonly ?string $privateKeyPassphrase = null,
        public readonly string  $atkBaseUrl           = 'https://fiskalizimi.atk-ks.org',
        public readonly string  $atkCouponPath        = '/pos/coupon',
        public readonly int     $atkTimeout           = 10,
    ) {}
}
