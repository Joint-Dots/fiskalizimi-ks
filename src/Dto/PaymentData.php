<?php

namespace Jointdots\FiskalizimiKs\Dto;

final class PaymentData
{
    public function __construct(
        public readonly PaymentType $type,
        public readonly int         $amount,  // fiscal units (× 100 per EUR)
    ) {}
}
