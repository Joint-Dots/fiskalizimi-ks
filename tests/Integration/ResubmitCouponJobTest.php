<?php

namespace Jointdots\FiskalizimiKs\Tests\Integration;

use Jointdots\FiskalizimiKs\Jobs\ResubmitCouponJob;
use Jointdots\FiskalizimiKs\Models\FiscalCoupon;
use Jointdots\FiskalizimiKs\Tests\TestCase;
use RuntimeException;

class ResubmitCouponJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations', '--realpath' => true]);
    }

    public function test_retry_deadline_is_fixed_at_48_hours(): void
    {
        $deadline = time() + (48 * 60 * 60);
        $job = new ResubmitCouponJob(1, $deadline);

        $this->assertSame($deadline, $job->retryUntil()->getTimestamp());
        $this->assertSame(0, $job->tries);
    }

    public function test_failed_job_marks_queued_coupon_as_failed(): void
    {
        $coupon = FiscalCoupon::query()->create([
            'fiscal_status' => FiscalCoupon::STATUS_QUEUED,
        ]);

        $job = new ResubmitCouponJob((int) $coupon->id);
        $job->failed(new RuntimeException('deadline expired'));

        $coupon->refresh();

        $this->assertSame(FiscalCoupon::STATUS_FAILED, $coupon->fiscal_status);
        $this->assertSame('deadline expired', $coupon->fiscal_error);
    }
}
