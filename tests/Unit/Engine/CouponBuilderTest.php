<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\CouponType;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;
use Jointdots\FiskalizimiKs\Engine\CouponBuilder;
use Jointdots\FiskalizimiKs\Engine\CouponSnapshot;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Generated\CouponType as ProtoCouponType;
use Jointdots\FiskalizimiKs\Tests\TestCase;

class CouponBuilderTest extends TestCase
{
    private CouponBuilder $builder;
    private CouponSnapshot $snapshot;
    private FiscalConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder  = new CouponBuilder();
        $this->snapshot = CouponSnapshot::generate(existsChecker: fn() => false);
        $this->config   = new FiscalConfig(
            businessId: 1001,
            applicationId: 42,
            posId: 1,
            branchId: 1,
            location: 'Test Location',
            privateKeyPath: '/dev/null',
        );
    }

    public function test_pos_coupon_and_citizen_coupon_share_identical_time(): void
    {
        $built = $this->builder->build($this->snapshot, $this->validCouponData(), $this->config, couponId: 99);

        $this->assertSame($built->posCoupon->getTime(), $built->citizenCoupon->getTime());
        $this->assertSame($this->snapshot->time, $built->posCoupon->getTime());
    }

    public function test_pos_coupon_and_citizen_coupon_share_identical_verification_no(): void
    {
        $built = $this->builder->build($this->snapshot, $this->validCouponData(), $this->config, couponId: 99);

        $this->assertSame($built->posCoupon->getVerificationNo(), $built->citizenCoupon->getVerificationNo());
        $this->assertSame($this->snapshot->verificationNo, $built->posCoupon->getVerificationNo());
    }

    public function test_pos_coupon_has_operator_id(): void
    {
        $built = $this->builder->build($this->snapshot, $this->validCouponData(), $this->config, couponId: 99);

        $this->assertSame('John', $built->posCoupon->getOperatorId());
    }

    public function test_payments_not_summing_to_total_throws(): void
    {
        $data = new CouponData(
            items:       [new ItemData('A', 1000, 'cope', 1.0, 500, 'D')],
            payments:    [new PaymentData(PaymentType::Cash, 999)],  // 999 ≠ 500
            operatorId:  'John',
        );

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/payment total/i');

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 1);
    }

    public function test_multi_tender_payments_accepted(): void
    {
        $data = new CouponData(
            items: [new ItemData('A', 10000, 'cope', 1.0, 1000, 'D')],
            payments: [
                new PaymentData(PaymentType::Cash, 600),
                new PaymentData(PaymentType::CreditCard, 400),
            ],
            operatorId: 'John',
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 5);

        $this->assertCount(2, iterator_to_array($built->posCoupon->getPayments()));
    }

    public function test_return_coupon_sets_reference_no(): void
    {
        $data = new CouponData(
            items:       [new ItemData('A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:    [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:  'John',
            type:        CouponType::Return,
            referenceNo: 77,
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 10);

        $this->assertSame(77, $built->posCoupon->getReferenceNo());
        $this->assertSame(ProtoCouponType::PBReturn, $built->posCoupon->getType());
        $this->assertSame(ProtoCouponType::PBReturn, $built->citizenCoupon->getType());
        $this->assertSame(3, $built->posCoupon->getType());
    }

    public function test_cancel_coupon_uses_atk_cancel_wire_value_and_reference(): void
    {
        $data = new CouponData(
            items:       [new ItemData('A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:    [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:  'John',
            type:        CouponType::Cancel,
            referenceNo: 77,
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 11);

        $this->assertSame(77, $built->posCoupon->getReferenceNo());
        $this->assertSame(ProtoCouponType::Cancel, $built->posCoupon->getType());
        $this->assertSame(ProtoCouponType::Cancel, $built->citizenCoupon->getType());
        $this->assertSame(2, $built->posCoupon->getType());
    }

    public function test_sale_coupon_uses_atk_sale_wire_value(): void
    {
        $built = $this->builder->build(
            $this->snapshot,
            $this->validCouponData(),
            $this->config,
            couponId: 12,
        );

        $this->assertSame(ProtoCouponType::Sale, $built->posCoupon->getType());
        $this->assertSame(ProtoCouponType::Sale, $built->citizenCoupon->getType());
        $this->assertSame(1, $built->posCoupon->getType());
        $this->assertSame(0, $built->posCoupon->getReferenceNo());
    }

    public function test_return_coupon_without_reference_no_throws(): void
    {
        $data = new CouponData(
            items:       [new ItemData('A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:    [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:  'John',
            type:        CouponType::Return,
        );

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/reference number/i');

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 10);
    }

    public function test_unknown_tax_code_throws(): void
    {
        $data = new CouponData(
            items:      [new ItemData('A', 10000, 'cope', 1.0, 1000, 'X')],
            payments:   [new PaymentData(PaymentType::Cash, 1000)],
            operatorId: 'John',
        );

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/tax code/i');

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 10);
    }

    public function test_sale_coupon_with_reference_no_throws(): void
    {
        $data = new CouponData(
            items:       [new ItemData('A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:    [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:  'John',
            referenceNo: 77,
        );

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/must not contain a reference/i');

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 10);
    }

    public function test_atk_base_url_must_use_https(): void
    {
        $config = new FiscalConfig(
            businessId: 1001,
            applicationId: 42,
            posId: 1,
            branchId: 1,
            location: 'Test Location',
            privateKeyPath: '/dev/null',
            atkBaseUrl: 'http://fiskalizimi.atk-ks.org',
        );

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/HTTPS/i');

        $this->builder->build($this->snapshot, $this->validCouponData(), $config, couponId: 10);
    }

    public function test_tax_groups_aggregated_by_rate(): void
    {
        $data = new CouponData(
            items: [
                new ItemData('A', 10000, 'cope', 1.0, 1000, 'D'),
                new ItemData('B', 20000, 'cope', 1.0, 2000, 'D'),
                new ItemData('C', 10000, 'cope', 1.0, 1000, 'E'),
            ],
            payments: [new PaymentData(PaymentType::Cash, 4000)],
            operatorId: 'John',
        );

        $built      = $this->builder->build($this->snapshot, $data, $this->config, couponId: 6);
        $taxGroups  = iterator_to_array($built->posCoupon->getTaxGroups());

        $this->assertCount(2, $taxGroups);
        $codes = array_map(fn($tg) => $tg->getTaxRate(), $taxGroups);
        $this->assertContains('D', $codes);
        $this->assertContains('E', $codes);
    }

    public function test_total_discount_is_added_to_pos_coupon(): void
    {
        $data = new CouponData(
            items:         [new ItemData('A', 10000, 'cope', 1.0, 900, 'D')],
            payments:      [new PaymentData(PaymentType::Cash, 900)],
            operatorId:    'John',
            totalDiscount: 100,
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 11);

        $this->assertSame(100, $built->posCoupon->getTotalDiscount());
    }

    public function test_total_discount_cannot_exceed_coupon_total(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessage('Total discount cannot exceed the coupon total.');

        $data = new CouponData(
            items:         [new ItemData('A', 10000, 'cope', 1.0, 900, 'D')],
            payments:      [new PaymentData(PaymentType::Cash, 900)],
            operatorId:    'John',
            totalDiscount: 901,
        );

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 12);
    }

    private function validCouponData(): CouponData
    {
        return new CouponData(
            items:      [new ItemData('Produkt A', 10000, 'cope', 1.0, 1000, 'D')],
            payments:   [new PaymentData(PaymentType::Cash, 1000)],
            operatorId: 'John',
        );
    }
}
