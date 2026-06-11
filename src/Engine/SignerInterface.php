<?php

declare(strict_types=1);

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Generated\PosCoupon;

interface SignerInterface
{
    public function sign(PosCoupon $coupon, FiscalConfig $config): SignedPayload;
}
