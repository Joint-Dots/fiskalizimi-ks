<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use Jointdots\FiskalizimiKs\Engine\CouponSnapshot;
use Jointdots\FiskalizimiKs\Tests\TestCase;

class CouponSnapshotTest extends TestCase
{
    public function test_generates_hex16_verification_number(): void
    {
        $snapshot = CouponSnapshot::generate(existsChecker: fn() => false);

        $this->assertMatchesRegularExpression('/^[A-F0-9]{16}$/', $snapshot->verificationNo);
    }

    public function test_verification_number_is_16_chars(): void
    {
        $snapshot = CouponSnapshot::generate(existsChecker: fn() => false);

        $this->assertSame(16, strlen($snapshot->verificationNo));
    }

    public function test_retries_until_unique(): void
    {
        $callCount = 0;
        $snapshot = CouponSnapshot::generate(existsChecker: function () use (&$callCount) {
            $callCount++;
            return $callCount < 3;
        });

        $this->assertSame(3, $callCount);
        $this->assertSame(16, strlen($snapshot->verificationNo));
    }

    public function test_time_is_unix_timestamp(): void
    {
        $before   = time();
        $snapshot = CouponSnapshot::generate(existsChecker: fn() => false);
        $after    = time();

        $this->assertGreaterThanOrEqual($before, $snapshot->time);
        $this->assertLessThanOrEqual($after, $snapshot->time);
    }

    public function test_two_snapshots_from_same_call_share_identical_time(): void
    {
        $snapshot = CouponSnapshot::generate(existsChecker: fn() => false);
        $time     = $snapshot->time;

        $this->assertSame($time, $snapshot->time);
    }
}
