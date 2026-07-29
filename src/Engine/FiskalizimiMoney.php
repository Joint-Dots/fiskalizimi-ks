<?php

namespace Jointdots\FiskalizimiKs\Engine;

final class FiskalizimiMoney
{
    public static function toFiscalUnits(float|int|string $amount): int
    {
        return self::toUnits($amount, 2);
    }

    public static function fromFiscalUnits(int $amount): float
    {
        return round($amount / 100, 2, PHP_ROUND_HALF_UP);
    }

    public static function toItemPriceUnits(float|int|string $amount): int
    {
        return self::toUnits($amount, 4);
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

    /**
     * Scale an amount to whole fiscal units, rounding a half away from zero.
     *
     * The scaling reads the amount's decimal digits rather than multiplying the
     * binary float, because a literal such as 1.005 is held as
     * 1.00499999999999989 and multiplying that by 100 lands just under the
     * half-way mark. PHP 8.3 and earlier concealed this: round() first snapped
     * its argument to fifteen significant digits, so it answered 101. PHP 8.4
     * dropped that compensation and answers 100 for the very same input, which
     * would leave a coupon's cents depending on the PHP build that produced it.
     * Rounding the digits the caller actually wrote holds every version to one
     * figure.
     */
    private static function toUnits(float|int|string $amount, int $scale): int
    {
        $decimal = self::decimalText($amount);

        if (preg_match('/^([+-]?)(\d*)(?:\.(\d*))?$/', $decimal, $parts) !== 1
            || ($parts[2] === '' && ($parts[3] ?? '') === '')) {
            return (int) round((float) $amount * 10 ** $scale, 0, PHP_ROUND_HALF_UP);
        }

        $fraction = str_pad($parts[3] ?? '', $scale + 1, '0');
        $units = (int) (($parts[2] === '' ? '0' : $parts[2]).substr($fraction, 0, $scale));

        if ($fraction[$scale] >= '5') {
            $units++;
        }

        return $parts[1] === '-' ? -$units : $units;
    }

    /**
     * Casting a float to string yields the shortest text that reads back as the
     * same float — the amount as it was written. Magnitudes far from the scales
     * money uses come back in exponent form, where no half-unit intent survives
     * to preserve.
     */
    private static function decimalText(float|int|string $amount): string
    {
        if (is_string($amount)) {
            return trim($amount);
        }

        $text = (string) $amount;

        return stripos($text, 'e') === false
            ? $text
            : sprintf('%.'.PHP_FLOAT_DIG.'F', $amount);
    }
}
