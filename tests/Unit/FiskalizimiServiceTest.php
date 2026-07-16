<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit;

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
use Jointdots\FiskalizimiKs\Engine\VerificationNo;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;
use Jointdots\FiskalizimiKs\FiskalizimiService;
use Jointdots\FiskalizimiKs\Models\FiscalCoupon;
use Jointdots\FiskalizimiKs\Tests\TestCase;
use Illuminate\Support\Facades\Bus;
use Mockery;

class FiskalizimiServiceTest extends TestCase
{
    private string $keyPath;
    private FiscalConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate a real throwaway P-256 key
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'svc_ec_') . '.pem';
        file_put_contents($this->keyPath, $pem);

        $this->config = new FiscalConfig(1001, 42, 1, 1, 'Location', $this->keyPath);

        // Run package migration on in-memory SQLite
        $this->artisan('migrate', ['--path' => 'database/migrations', '--realpath' => true]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        @unlink($this->keyPath);
        Mockery::close();
    }

    public function test_successful_fiscalization_returns_fiscalized_result(): void
    {
        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()->andReturn(9912345);

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $result  = $service->fiscalize($this->couponData(), $this->config);

        $this->assertSame(FiscalStatus::Fiscalized, $result->status);
        $this->assertSame(9912345, $result->transactionNo);
        $this->assertNotEmpty($result->verificationNo);
        $this->assertNotEmpty($result->citizenQr);
        $this->assertStringContainsString('|', $result->citizenQr);
    }

    /**
     * The regulation requires the NUIKF to be unique, not sequential, so a
     * caller without its own counter can omit it and get a generated one.
     */
    public function test_fiscalize_without_a_verification_number_generates_one(): void
    {
        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()->andReturn(9912345);

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $result  = $service->fiscalize($this->couponData(verificationNo: null), $this->config);

        $this->assertSame(FiscalStatus::Fiscalized, $result->status);
        $this->assertMatchesRegularExpression(VerificationNo::PATTERN, $result->verificationNo);
    }

    public function test_atk_failure_returns_queued_result_with_qr(): void
    {
        Bus::fake();

        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()->andThrow(new FiscalSubmissionException('timeout'));

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $result  = $service->fiscalize($this->couponData(), $this->config);

        $this->assertSame(FiscalStatus::Queued, $result->status);
        $this->assertNull($result->transactionNo);
        $this->assertNotEmpty($result->citizenQr, 'QR must be present even when ATK is unreachable');
    }

    public function test_duplicate_idempotency_key_returns_existing_result_without_calling_atk(): void
    {
        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()->andReturn(111);

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $data    = $this->couponData('idem-key-001');

        $first  = $service->fiscalize($data, $this->config);
        $second = $service->fiscalize($data, $this->config);  // ATK must NOT be called again

        $this->assertSame($first->journalId, $second->journalId);
        $this->assertSame($first->verificationNo, $second->verificationNo);
    }

    public function test_journal_row_is_created_with_correct_fiscal_time(): void
    {
        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()->andReturn(222);

        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);
        $result  = $service->fiscalize($this->couponData(), $this->config);

        $coupon = FiscalCoupon::findOrFail($result->journalId);
        $this->assertSame($result->fiscalTime, $coupon->fiscal_time);
        $this->assertSame($result->verificationNo, $coupon->fiscal_verification_no);
    }

    public function test_signing_failure_marks_journal_as_failed(): void
    {
        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldNotReceive('submit');

        $invalidConfig = new FiscalConfig(1001, 42, 1, 1, 'Location', 'missing-key.pem');
        $service = new FiskalizimiService(new CouponBuilder(), new Signer(), new QrGenerator(), $atk);

        try {
            $service->fiscalize($this->couponData(), $invalidConfig);
            $this->fail('Expected fiscalization to fail when the signing key is missing.');
        } catch (FiscalConfigurationException) {
            $coupon = FiscalCoupon::query()->firstOrFail();
            $this->assertSame(FiscalCoupon::STATUS_FAILED, $coupon->fiscal_status);
            $this->assertNotEmpty($coupon->fiscal_error);
        }
    }

    private function couponData(
        ?string $idempotencyKey = null,
        ?string $verificationNo = '0000000000000001',
    ): CouponData {
        return new CouponData(
            items:          [new ItemData('Produkt A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:       [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:     'Cashier',
            idempotencyKey: $idempotencyKey,
            verificationNo: $verificationNo,
        );
    }
}
