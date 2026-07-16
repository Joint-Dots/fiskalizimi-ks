<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;

final class CouponSnapshot
{
    private function __construct(
        public readonly string $verificationNo,
        public readonly int    $time,
    ) {}

    /**
     * Captures the two identifiers that must stay identical across the POS and
     * citizen payloads for the lifetime of one coupon.
     *
     * Supply a $verificationNo to use an application-owned NUIKF; omit it and
     * one is generated. See VerificationNo for the conformance rules.
     *
     * @param callable(string): bool $existsChecker Returns true if verificationNo already exists
     */
    public static function generate(
        callable $existsChecker,
        ?string $verificationNo = null,
        ?int $time = null,
    ): self {
        if ($verificationNo === null) {
            return new self(
                verificationNo: VerificationNo::generateUnique($existsChecker),
                time: $time ?? now()->timestamp,
            );
        }

        VerificationNo::assertValid($verificationNo);

        if ($existsChecker($verificationNo)) {
            throw new FiscalConfigurationException('Verification number already exists.');
        }

        return new self(verificationNo: $verificationNo, time: $time ?? now()->timestamp);
    }
}
