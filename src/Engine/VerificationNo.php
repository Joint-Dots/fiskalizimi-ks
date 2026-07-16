<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;

/**
 * The NUIKF (Numri unik identifikues i kuponit fiskal).
 *
 * Kerkesat Specifike Teknike dhe Funksionale, point 10, requires it to be
 * alphanumeric, a single unique value with no divisions, dashes or special
 * characters, and at most 16 characters long.
 *
 * The regulation requires the value to be *unique*, not sequential. The phrase
 * "ne renditje" constrains how the characters are arranged within the one
 * unbroken value; it does not require a coupon's NUIKF to exceed the previous
 * coupon's. A random 16-character value therefore conforms, which is why this
 * package can generate one rather than demanding the caller own a counter.
 */
final class VerificationNo
{
    /**
     * The D modifier anchors $ to the true end of the subject. Without it PHP
     * also matches before a trailing newline, so "0000000000000001\n" — 17
     * characters — would pass as valid and reach the signed payload.
     */
    public const PATTERN = '/^[A-Z0-9]{1,16}$/D';

    private const MAX_GENERATION_ATTEMPTS = 5;

    public static function assertValid(string $verificationNo): void
    {
        if (!preg_match(self::PATTERN, $verificationNo)) {
            throw new FiscalConfigurationException(
                'Verification number must be 1-16 uppercase alphanumeric characters.'
            );
        }
    }

    public static function random(): string
    {
        return strtoupper(bin2hex(random_bytes(8)));
    }

    /**
     * Retries on collision. Bounded so a misbehaving checker fails loudly
     * instead of hanging the request forever.
     *
     * @param callable(string): bool $existsChecker Returns true if the value already exists
     */
    public static function generateUnique(callable $existsChecker): string
    {
        for ($attempt = 0; $attempt < self::MAX_GENERATION_ATTEMPTS; $attempt++) {
            $candidate = self::random();

            if (!$existsChecker($candidate)) {
                return $candidate;
            }
        }

        throw new FiscalConfigurationException(sprintf(
            'Could not generate a unique verification number after %d attempts.',
            self::MAX_GENERATION_ATTEMPTS,
        ));
    }
}
