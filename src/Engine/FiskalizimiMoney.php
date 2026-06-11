<?php

namespace Jointdots\FiskalizimiKs\Engine;

final class FiskalizimiMoney
{
    public static function toFiscalUnits(float|int|string $amount): int
    {
        return (int) round((float) $amount * 100, 0, PHP_ROUND_HALF_UP);
    }

    public static function fromFiscalUnits(int $amount): float
    {
        return round($amount / 100, 2, PHP_ROUND_HALF_UP);
    }

    public static function toItemPriceUnits(float|int|string $amount): int
    {
        return (int) round((float) $amount * 10000, 0, PHP_ROUND_HALF_UP);
    }

    public static function fromItemPriceUnits(int $amount): float
    {
        return round($amount / 10000, 4, PHP_ROUND_HALF_UP);
    }
}
