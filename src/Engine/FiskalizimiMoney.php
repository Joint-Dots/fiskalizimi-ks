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

    /**
     * An item carries its money in the same ten-thousandths-of-a-euro units as
     * its unit price; a coupon carries its own in cents. This is the one
     * conversion between the two scales, so every figure that crosses from an
     * item to its coupon rounds the same way.
     *
     * ATK's materials disagree on the item total: the reference POS samples put
     * it in cents, while the verification portal reads it in item units. A
     * sandbox coupon submitted on 2026-07-27 rendered correctly — and its
     * per-item arithmetic reconciled — only in item units.
     */
    public static function itemUnitsToFiscalUnits(int $itemUnits): int
    {
        return (int) round($itemUnits / 100, 0, PHP_ROUND_HALF_UP);
    }
}
