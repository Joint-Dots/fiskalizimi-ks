<?php

namespace Jointdots\FiskalizimiKs\Tests\Integration;

use Illuminate\Support\Facades\Bus;
use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Dto\FiscalStatus;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;
use Jointdots\FiskalizimiKs\Engine\AtkClientInterface;
use Jointdots\FiskalizimiKs\Engine\CouponBuilder;
use Jointdots\FiskalizimiKs\Engine\QrGenerator;
use Jointdots\FiskalizimiKs\Engine\Signer;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;
use Jointdots\FiskalizimiKs\FiskalizimiService;
use Jointdots\FiskalizimiKs\Jobs\ResubmitCouponJob;
use Jointdots\FiskalizimiKs\Tests\TestCase;
use Mockery;

class AutoDispatchFlagTest extends TestCase
{
    private string $keyPath;
    private FiscalConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'auto_dispatch_ec_') . '.pem';
        file_put_contents($this->keyPath, $pem);

        $this->config = new FiscalConfig(1001, 42, 1, 1, 'Location', $this->keyPath);

        $this->artisan('migrate', ['--path' => 'database/migrations', '--realpath' => true]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        @unlink($this->keyPath);
        Mockery::close();
    }

    public function test_no_resubmit_job_dispatched_when_flag_disabled(): void
    {
        config()->set('fiskalizimi.retry.auto_dispatch', false);
        Bus::fake();

        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()
            ->andThrow(new FiscalSubmissionException('timeout'));

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $result  = $service->fiscalize($this->couponData(), $this->config);

        $this->assertSame(FiscalStatus::Queued, $result->status);
        Bus::assertNotDispatched(ResubmitCouponJob::class);
    }

    public function test_resubmit_job_dispatched_when_flag_enabled(): void
    {
        config()->set('fiskalizimi.retry.auto_dispatch', true);
        Bus::fake();

        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()
            ->andThrow(new FiscalSubmissionException('timeout'));

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $service->fiscalize($this->couponData(), $this->config);

        Bus::assertDispatched(ResubmitCouponJob::class);
    }

    private function couponData(): CouponData
    {
        return new CouponData(
            items:      [new ItemData('Produkt A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:   [new PaymentData(PaymentType::Cash, 1000)],
            operatorId: 'Cashier',
        );
    }
}
