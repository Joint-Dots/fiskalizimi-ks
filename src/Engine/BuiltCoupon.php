<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Generated\CitizenCoupon;
use Jointdots\FiskalizimiKs\Generated\PosCoupon;

final class BuiltCoupon
{
    public function __construct(
        public readonly PosCoupon     $posCoupon,
        public readonly CitizenCoupon $citizenCoupon,
    ) {}
}
