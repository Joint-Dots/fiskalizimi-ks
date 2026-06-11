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
     * @param callable(string): bool $existsChecker Returns true if verificationNo already exists
     */
    public static function generate(
        callable $existsChecker,
        ?string $verificationNo = null,
        ?int $time = null,
    ): self {
        if ($verificationNo !== null) {
            if (!preg_match('/^[A-F0-9]{16}$/', $verificationNo)) {
                throw new FiscalConfigurationException(
                    'Verification number must be exactly 16 uppercase hexadecimal characters.'
                );
            }

            if ($existsChecker($verificationNo)) {
                throw new FiscalConfigurationException('Verification number already exists.');
            }

            return new self(verificationNo: $verificationNo, time: $time ?? now()->timestamp);
        }

        do {
            $no = strtoupper(bin2hex(random_bytes(8)));
        } while ($existsChecker($no));

        return new self(verificationNo: $no, time: $time ?? now()->timestamp);
    }
}
