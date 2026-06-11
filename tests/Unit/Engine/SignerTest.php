<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Engine\CouponBuilder;
use Jointdots\FiskalizimiKs\Engine\CouponSnapshot;
use Jointdots\FiskalizimiKs\Engine\Signer;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Tests\TestCase;
use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;

class SignerTest extends TestCase
{
    private string $ec256KeyPath;
    private string $rsaKeyPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate a throwaway P-256 key for testing
        $ecKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($ecKey, $ecPem);
        $this->ec256KeyPath = tempnam(sys_get_temp_dir(), 'ec256_') . '.pem';
        file_put_contents($this->ec256KeyPath, $ecPem);

        // Generate a throwaway RSA key for rejection test
        $rsaKey = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048]);
        openssl_pkey_export($rsaKey, $rsaPem);
        $this->rsaKeyPath = tempnam(sys_get_temp_dir(), 'rsa_') . '.pem';
        file_put_contents($this->rsaKeyPath, $rsaPem);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        @unlink($this->ec256KeyPath);
        @unlink($this->rsaKeyPath);
    }

    public function test_signs_pos_coupon_and_returns_base64_payload(): void
    {
        $signer   = new Signer();
        $config   = $this->configWithKey($this->ec256KeyPath);
        $coupon   = $this->buildCoupon($config);
        $payload  = $signer->sign($coupon, $config);

        $this->assertNotEmpty($payload->details);
        $this->assertNotEmpty($payload->signature);
        $this->assertNotFalse(base64_decode($payload->details, true));
        $this->assertNotFalse(base64_decode($payload->signature, true));
    }

    public function test_signature_is_verifiable_with_public_key(): void
    {
        $signer  = new Signer();
        $config  = $this->configWithKey($this->ec256KeyPath);
        $coupon  = $this->buildCoupon($config);
        $payload = $signer->sign($coupon, $config);

        $ecKey     = openssl_pkey_get_private(file_get_contents($this->ec256KeyPath));
        $publicKey = openssl_pkey_get_details($ecKey)['key'];

        $verified = openssl_verify(
            $payload->details,
            base64_decode($payload->signature),
            $publicKey,
            OPENSSL_ALGO_SHA256
        );

        $this->assertSame(1, $verified);
    }

    public function test_rsa_key_throws_configuration_exception(): void
    {
        $signer = new Signer();
        $config = $this->configWithKey($this->rsaKeyPath);
        $coupon = $this->buildCoupon(new FiscalConfig(1, 1, 1, 1, 'L', $this->ec256KeyPath));

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/ECDSA/i');

        $signer->sign($coupon, $config);
    }

    private function configWithKey(string $keyPath): FiscalConfig
    {
        return new FiscalConfig(1001, 42, 1, 1, 'Test', $keyPath);
    }

    private function buildCoupon(FiscalConfig $config): \Jointdots\FiskalizimiKs\Generated\PosCoupon
    {
        $snapshot = CouponSnapshot::generate(existsChecker: fn() => false);
        $data     = new CouponData(
            items:      [new ItemData('A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:   [new PaymentData(PaymentType::Cash, 1000)],
            operatorId: 'Test',
        );
        return (new CouponBuilder())->build($snapshot, $data, $config, 1)->posCoupon;
    }
}
