<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSigningException;
use Jointdots\FiskalizimiKs\Generated\CitizenCoupon;

final class QrGenerator
{
    public function generate(CitizenCoupon $coupon, FiscalConfig $config): string
    {
        $key     = KeyLoader::load($config->privateKeyPath, $config->privateKeyPassphrase);
        $details = base64_encode($coupon->serializeToString());

        $signature = '';
        if (!openssl_sign($details, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new FiscalSigningException('CitizenCoupon QR signing failed.');
        }

        return $details . '|' . base64_encode($signature);
    }
}
