<?php

namespace Jointdots\FiskalizimiKs\Tests\Feature;

use Jointdots\FiskalizimiKs\Engine\AtkClientInterface;
use Jointdots\FiskalizimiKs\Engine\VerificationNo;
use Jointdots\FiskalizimiKs\Models\FiscalCoupon;
use Jointdots\FiskalizimiKs\Tests\TestCase;
use Mockery;

/**
 * The package registers and ships these routes itself, so they are part of its
 * public surface. Nothing else in the suite drives the HTTP layer, which is how
 * a CouponData that FiscalizeRequest could not populate reached a green build.
 */
class FiscalizeEndpointTest extends TestCase
{
    private string $keyPath;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'api_ec_') . '.pem';
        file_put_contents($this->keyPath, $pem);

        $app['config']->set('fiskalizimi.api.enabled', true);
        $app['config']->set('fiskalizimi.api.token', 'test-token');
        $app['config']->set('fiskalizimi.business', [
            'id'             => 1001,
            'application_id' => 42,
            'pos_id'         => 1,
            'branch_id'      => 1,
            'location'       => 'Test Location',
            'key_path'       => $this->keyPath,
            'key_passphrase' => null,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate', ['--path' => 'database/migrations', '--realpath' => true]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        Mockery::close();

        parent::tearDown();
    }

    public function test_post_coupons_fiscalizes_when_no_verification_no_is_supplied(): void
    {
        $this->fakeAtk();

        $response = $this->withToken('test-token')->postJson('api/fiscal/coupons', $this->payload());

        $response->assertOk();
        $response->assertJsonPath('status', 'fiscalized');
        $response->assertJsonPath('transaction_no', 9912345);
        $this->assertMatchesRegularExpression(VerificationNo::PATTERN, $response->json('verification_no'));
        $this->assertStringContainsString('|', $response->json('citizen_qr'));
    }

    /** An application that owns a counter may still supply its own NUIKF. */
    public function test_post_coupons_accepts_an_application_supplied_verification_no(): void
    {
        $this->fakeAtk();

        $response = $this->withToken('test-token')->postJson(
            'api/fiscal/coupons',
            $this->payload(['verification_no' => 'ZZZZ999GGGG00001']),
        );

        $response->assertOk();
        $response->assertJsonPath('verification_no', 'ZZZZ999GGGG00001');
    }

    public function test_post_coupons_rejects_a_malformed_verification_no(): void
    {
        $response = $this->withToken('test-token')->postJson(
            'api/fiscal/coupons',
            $this->payload(['verification_no' => 'not-valid!']),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('verification_no');
    }

    /**
     * A coupon held for an operator decision is neither accepted, nor rejected,
     * nor still in progress. Answering 202 would tell the caller to wait for a
     * resolution that will never arrive on its own.
     */
    public function test_post_coupons_reports_an_unresolved_coupon_distinctly(): void
    {
        $this->fakeAtk();

        $payload = $this->payload(['idempotency_key' => 'held-for-operator']);
        $this->withToken('test-token')->postJson('api/fiscal/coupons', $payload);

        FiscalCoupon::query()->where('idempotency_key', 'held-for-operator')
            ->update(['fiscal_status' => FiscalCoupon::STATUS_UNRESOLVED]);

        $response = $this->withToken('test-token')->postJson('api/fiscal/coupons', $payload);

        $response->assertStatus(409);
        $response->assertJsonPath('status', 'unresolved');
    }

    public function test_post_coupons_requires_a_bearer_token(): void
    {
        $this->postJson('api/fiscal/coupons', $this->payload())->assertStatus(401);
    }

    private function fakeAtk(): void
    {
        $atk = Mockery::mock(AtkClientInterface::class);
        $atk->shouldReceive('submit')->once()->andReturn(9912345);

        $this->app->instance(AtkClientInterface::class, $atk);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'idempotency_key' => 'order-' . uniqid(),
            'operator_id'     => 'Cashier',
            'items'           => [[
                'name'     => 'Produkt A',
                'price'    => 10000,
                'unit'     => 'cope',
                'quantity' => 1.0,
                'total'    => 100000, // item units: EUR 10.0000
                'tax_rate' => 'D',
            ]],
            'payments'        => [['type' => 'cash', 'amount' => 1000]],
            'total'           => 1000,
        ], $overrides);
    }
}
