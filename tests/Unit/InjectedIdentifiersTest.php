<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit;

use Jointdots\FiskalizimiKs\Engine\CouponSnapshot;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Tests\TestCase;

class InjectedIdentifiersTest extends TestCase
{
    public function test_snapshot_uses_injected_verification_and_time(): void
    {
        $snap = CouponSnapshot::generate(
            existsChecker: fn (string $no) => false,
            verificationNo: 'ABCD1234ABCD1234',
            time: 1_700_000_000,
        );

        $this->assertSame('ABCD1234ABCD1234', $snap->verificationNo);
        $this->assertSame(1_700_000_000, $snap->time);
    }

    public function test_snapshot_generates_when_not_injected(): void
    {
        $snap = CouponSnapshot::generate(existsChecker: fn (string $no) => false);

        $this->assertSame(16, strlen($snap->verificationNo));
        $this->assertGreaterThan(0, $snap->time);
    }

    public function test_snapshot_retries_when_exists_checker_returns_true(): void
    {
        $calls = 0;
        // Return true on first call (collision), false on second
        $checker = function (string $no) use (&$calls): bool {
            return ++$calls === 1;
        };

        $snap = CouponSnapshot::generate(existsChecker: $checker);

        $this->assertSame(2, $calls);
        $this->assertSame(16, strlen($snap->verificationNo));
    }

    public function test_snapshot_uses_injected_verification_with_generated_time(): void
    {
        $snap = CouponSnapshot::generate(
            existsChecker: fn (string $no) => false,
            verificationNo: 'ABCD1234ABCD1234',
            // time not injected
        );

        $this->assertSame('ABCD1234ABCD1234', $snap->verificationNo);
        $this->assertGreaterThan(0, $snap->time);
    }

    public function test_injected_verification_number_must_be_unique(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/already exists/i');

        CouponSnapshot::generate(
            existsChecker: fn (string $no) => true,
            verificationNo: 'ABCD1234ABCD1234',
        );
    }

    public function test_injected_verification_number_must_match_atk_format(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/16 uppercase alphanumeric/i');

        CouponSnapshot::generate(
            existsChecker: fn (string $no) => false,
            verificationNo: 'not-valid',
        );
    }
}
