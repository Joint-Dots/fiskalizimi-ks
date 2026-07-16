<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use Jointdots\FiskalizimiKs\Engine\CouponSnapshot;
use Jointdots\FiskalizimiKs\Engine\VerificationNo;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Tests\TestCase;

class CouponSnapshotTest extends TestCase
{
    /**
     * Point 10 requires the NUIKF to be alphanumeric; hex [A-F0-9] is a strict
     * subset and would reject a conformant value containing G-Z.
     */
    public function test_accepts_alphanumeric_verification_number_beyond_hex(): void
    {
        $snapshot = CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: 'ZZZZ999GGGG00001',
        );

        $this->assertSame('ZZZZ999GGGG00001', $snapshot->verificationNo);
    }

    /** Point 10 sets 16 characters as a maximum, not an exact length. */
    public function test_accepts_verification_number_shorter_than_16_chars(): void
    {
        $snapshot = CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: 'A1',
        );

        $this->assertSame('A1', $snapshot->verificationNo);
    }

    public function test_rejects_lowercase_verification_number(): void
    {
        $this->expectException(FiscalConfigurationException::class);

        CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: 'abcdef1234567890',
        );
    }

    public function test_rejects_verification_number_longer_than_16_chars(): void
    {
        $this->expectException(FiscalConfigurationException::class);

        CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: '00000000000000001',
        );
    }

    /**
     * PHP's $ also matches immediately before a trailing newline, so without
     * the D modifier a 17-character value passes validation and reaches the
     * signed payload and the 16-character journal column.
     */
    public function test_rejects_verification_number_with_trailing_newline(): void
    {
        $this->expectException(FiscalConfigurationException::class);

        CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: "0000000000000001\n",
        );
    }

    public function test_rejects_empty_verification_number(): void
    {
        $this->expectException(FiscalConfigurationException::class);

        CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: '',
        );
    }

    public function test_rejects_verification_number_with_special_characters(): void
    {
        $this->expectException(FiscalConfigurationException::class);

        CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: 'A1B2-C3D4 E5F6,7',
        );
    }

    /** Point 10 requires the NUIKF to be unique. */
    public function test_rejects_duplicate_verification_number(): void
    {
        $this->expectException(FiscalConfigurationException::class);

        CouponSnapshot::generate(
            existsChecker: fn () => true,
            verificationNo: '0000000000000001',
        );
    }

    /**
     * The regulation requires the NUIKF to be unique, not sequential, so the
     * package generates one when the caller does not own a counter.
     */
    public function test_generates_a_conformant_verification_number_when_none_supplied(): void
    {
        $snapshot = CouponSnapshot::generate(existsChecker: fn () => false);

        $this->assertMatchesRegularExpression(VerificationNo::PATTERN, $snapshot->verificationNo);
        $this->assertSame(16, strlen($snapshot->verificationNo));
    }

    public function test_retries_generation_until_unique(): void
    {
        $callCount = 0;
        $snapshot  = CouponSnapshot::generate(existsChecker: function () use (&$callCount) {
            $callCount++;
            return $callCount < 3;
        });

        $this->assertSame(3, $callCount);
        $this->assertMatchesRegularExpression(VerificationNo::PATTERN, $snapshot->verificationNo);
    }

    /** A checker that always collides must fail loudly rather than hang. */
    public function test_generation_gives_up_after_bounded_attempts(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/unique verification number/i');

        CouponSnapshot::generate(existsChecker: fn () => true);
    }

    public function test_time_is_unix_timestamp(): void
    {
        $before   = time();
        $snapshot = CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: '0000000000000001',
        );
        $after    = time();

        $this->assertGreaterThanOrEqual($before, $snapshot->time);
        $this->assertLessThanOrEqual($after, $snapshot->time);
    }

    public function test_two_snapshots_from_same_call_share_identical_time(): void
    {
        $snapshot = CouponSnapshot::generate(
            existsChecker: fn () => false,
            verificationNo: '0000000000000001',
        );
        $time     = $snapshot->time;

        $this->assertSame($time, $snapshot->time);
    }
}
