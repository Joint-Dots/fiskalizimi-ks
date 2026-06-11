<?php

namespace Jointdots\FiskalizimiKs\Tests\Integration;

use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Dto\FiscalStatus;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;
use Jointdots\FiskalizimiKs\Engine\AtkClient;
use Jointdots\FiskalizimiKs\Engine\CouponBuilder;
use Jointdots\FiskalizimiKs\Engine\QrGenerator;
use Jointdots\FiskalizimiKs\Engine\Signer;
use Jointdots\FiskalizimiKs\FiskalizimiService;
use Jointdots\FiskalizimiKs\Tests\TestCase;

class AtkSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!getenv('ATK_TEST_KEY_PATH')) {
            $this->markTestSkipped('ATK_TEST_KEY_PATH not set — skipping live ATK integration test');
        }
    }

    public function test_submits_sale_coupon_to_atk_test_environment_and_gets_transaction_no(): void
    {
        $config = new FiscalConfig(
            businessId:     (int) getenv('ATK_TEST_BUSINESS_ID'),
            applicationId:  (int) getenv('ATK_TEST_APPLICATION_ID'),
            posId:          (int) getenv('ATK_TEST_POS_ID'),
            branchId:       (int) getenv('ATK_TEST_BRANCH_ID'),
            location:       (string) getenv('ATK_TEST_LOCATION'),
            privateKeyPath: (string) getenv('ATK_TEST_KEY_PATH'),
            atkBaseUrl:     (string) (getenv('ATK_TEST_BASE_URL') ?: 'https://fiskalizimi-test.atk-ks.org'),
            atkCouponPath:  '/pos/coupon',
            atkTimeout:     15,
        );

        $this->artisan('migrate', ['--path' => 'database/migrations', '--realpath' => true]);

        $service = new FiskalizimiService(
            new CouponBuilder(),
            new Signer(),
            new QrGenerator(),
            new AtkClient(),
        );

        $data = new CouponData(
            items: [
                new ItemData('Test Product', 10000, 'cope', 1.0, 1000, 'D'),
            ],
            payments: [
                new PaymentData(PaymentType::Cash, 1000),
            ],
            operatorId:     'TestCashier',
            idempotencyKey: 'integration-test-' . uniqid(),
        );

        $result = $service->fiscalize($data, $config);

        $this->assertSame(FiscalStatus::Fiscalized, $result->status);
        $this->assertNotNull($result->transactionNo);
        $this->assertGreaterThan(0, $result->transactionNo);
        $this->assertSame(16, strlen($result->verificationNo));
        $this->assertStringContainsString('|', $result->citizenQr);
    }
}
