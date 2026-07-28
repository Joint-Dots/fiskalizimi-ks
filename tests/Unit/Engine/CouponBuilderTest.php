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
        $this->snapshot = CouponSnapshot::generate(
            existsChecker: fn() => false,
            verificationNo: '0000000000000001',
        );
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

    /**
     * ATK verifies the citizen QR against the submitted POS payload, so every
     * field the two encodings share must agree. Multiple tax groups are used
     * deliberately: a per-rate comparison that collapses duplicates would miss
     * a divergence in group count.
     */
    public function test_pos_and_citizen_coupons_agree_on_every_shared_field(): void
    {
        $data = new CouponData(
            items: [
                new ItemData('Produkt A', 10000, 'cope', 1.0, 100000, 'D'),
                new ItemData('Produkt B', 20000, 'cope', 1.0, 200000, 'E'),
                new ItemData('Produkt C', 5000, 'cope', 1.0, 50000, 'D'),
            ],
            payments:   [new PaymentData(PaymentType::Cash, 3500)],
            operatorId: 'Cashier',
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 99);
        $pos     = $built->posCoupon;
        $citizen = $built->citizenCoupon;

        $this->assertSame($pos->getBusinessId(), $citizen->getBusinessId());
        $this->assertSame($pos->getCouponId(), $citizen->getCouponId());
        $this->assertSame($pos->getBranchId(), $citizen->getBranchId());
        $this->assertSame($pos->getPosId(), $citizen->getPosId());
        $this->assertSame($pos->getVerificationNo(), $citizen->getVerificationNo());
        $this->assertSame($pos->getType(), $citizen->getType());
        $this->assertSame($pos->getTime(), $citizen->getTime());
        $this->assertSame($pos->getTotal(), $citizen->getTotal());
        $this->assertSame($pos->getTotalTax(), $citizen->getTotalTax());
        $this->assertSame($pos->getTotalNoTax(), $citizen->getTotalNoTax());

        $this->assertSame(
            $this->taxGroupTuples($pos->getTaxGroups()),
            $this->taxGroupTuples($citizen->getTaxGroups()),
        );
        $this->assertCount(2, $this->taxGroupTuples($pos->getTaxGroups()));
    }

    /** @return list<array{string, int, int}> */
    private function taxGroupTuples(iterable $groups): array
    {
        $tuples = [];

        foreach ($groups as $group) {
            $tuples[] = [$group->getTaxRate(), $group->getTotalForTax(), $group->getTotalTax()];
        }

        return $tuples;
    }

    /**
     * The builder must accept every NUIKF the snapshot accepts: point 10 is
     * alphanumeric, and hex [A-F0-9] would reject a conformant value with G-Z.
     */
    public function test_builds_with_an_alphanumeric_verification_number(): void
    {
        $snapshot = CouponSnapshot::generate(
            existsChecker: fn() => false,
            verificationNo: 'ZZZZ999GGGG00001',
        );

        $built = $this->builder->build($snapshot, $this->validCouponData(), $this->config, couponId: 99);

        $this->assertSame('ZZZZ999GGGG00001', $built->posCoupon->getVerificationNo());
    }

    public function test_pos_coupon_has_operator_id(): void
    {
        $built = $this->builder->build($this->snapshot, $this->validCouponData(), $this->config, couponId: 99);

        $this->assertSame('John', $built->posCoupon->getOperatorId());
    }

    public function test_payments_not_summing_to_total_throws(): void
    {
        $data = new CouponData(
            items:       [new ItemData('A', 1000, 'cope', 1.0, 50000, 'D')],
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
            items: [new ItemData('A', 10000, 'cope', 1.0, 100000, 'D')],
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
            items:       [new ItemData('A', 10000, 'cope', 1.0, 100000, 'D')],
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
            items:       [new ItemData('A', 10000, 'cope', 1.0, 100000, 'D')],
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
            items:       [new ItemData('A', 10000, 'cope', 1.0, 100000, 'D')],
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
            items:      [new ItemData('A', 10000, 'cope', 1.0, 100000, 'X')],
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
            items:       [new ItemData('A', 10000, 'cope', 1.0, 100000, 'D')],
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

    /**
     * An item's money is carried in item units and a coupon's in cents, so the
     * same amount appears at two scales in one payload. The item rows must reach
     * the wire untouched while every coupon-level figure is the converted view —
     * which is what ATK's verification portal reconciles a coupon against.
     */
    public function test_item_rows_keep_item_units_while_coupon_totals_are_cents(): void
    {
        $data = new CouponData(
            items: [
                new ItemData('A', 10000, 'cope', 1.0, 100000, 'D'),  // EUR 10.00
                new ItemData('B', 5000, 'cope', 1.0, 50000, 'E'),    // EUR  5.00
            ],
            payments:   [new PaymentData(PaymentType::Cash, 1500)],
            operatorId: 'John',
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 21);
        $items = iterator_to_array($built->posCoupon->getItems());

        $this->assertSame(100000, $items[0]->getTotal());
        $this->assertSame(50000, $items[1]->getTotal());

        $this->assertSame(1500, $built->posCoupon->getTotal());
        $this->assertSame(1500, $built->citizenCoupon->getTotal());

        $taxTotal = 0;

        foreach ($built->posCoupon->getTaxGroups() as $group) {
            $taxTotal += $group->getTotalForTax() + $group->getTotalTax();
        }

        $this->assertSame(1500, $taxTotal);
    }

    /**
     * The caller's own record is kept in cents, so it is checked against the
     * converted total. An expectation stated in item units must not pass.
     */
    public function test_callers_expected_total_is_compared_in_cents(): void
    {
        $data = new CouponData(
            items:         [new ItemData('A', 10000, 'cope', 1.0, 100000, 'D')],
            payments:      [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:    'John',
            expectedTotal: 100000,
        );

        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessageMatches('/does not match the caller\'s record/i');

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 22);
    }

    public function test_tax_groups_aggregated_by_rate(): void
    {
        $data = new CouponData(
            items: [
                new ItemData('A', 10000, 'cope', 1.0, 100000, 'D'),
                new ItemData('B', 20000, 'cope', 1.0, 200000, 'D'),
                new ItemData('C', 10000, 'cope', 1.0, 100000, 'E'),
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
            items:         [new ItemData('A', 10000, 'cope', 1.0, 90000, 'D')],
            payments:      [new PaymentData(PaymentType::Cash, 900)],
            operatorId:    'John',
            totalDiscount: 100,
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 11);

        $this->assertSame(100, $built->posCoupon->getTotalDiscount());
    }

    /**
     * An item's Total is what is left after its markdown, so the coupon total and
     * the discount are disjoint amounts: the pre-discount subtotal is their sum,
     * and a discount can never exceed it. Capping the discount at the total was
     * therefore not a weak bound but a wrong one — it rejected every coupon marked
     * down by more than half, where the money taken off exceeds the money left.
     */
    public function test_a_coupon_discounted_by_more_than_half_is_accepted(): void
    {
        $data = new CouponData(
            items:         [new ItemData('A', 10000, 'cope', 1.0, 4000, 'D')],
            payments:      [new PaymentData(PaymentType::Cash, 40)],
            operatorId:    'John',
            totalDiscount: 60,
        );

        $built = $this->builder->build($this->snapshot, $data, $this->config, couponId: 12);

        $this->assertSame(40, $built->posCoupon->getTotal());
        $this->assertSame(60, $built->posCoupon->getTotalDiscount());
    }

    public function test_total_discount_cannot_be_negative(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessage('Total discount cannot be negative.');

        $data = new CouponData(
            items:         [new ItemData('A', 10000, 'cope', 1.0, 90000, 'D')],
            payments:      [new PaymentData(PaymentType::Cash, 900)],
            operatorId:    'John',
            totalDiscount: -1,
        );

        $this->builder->build($this->snapshot, $data, $this->config, couponId: 22);
    }

    /**
     * ATK's item-name budget is applied in bytes, but a byte-wise cut can split a
     * UTF-8 sequence in half. Protobuf then refuses the string ("Expect utf-8
     * encoding") from inside serialization, which reaches the caller as a sale
     * that cannot be fiscalized and fails identically on every retry. Albanian
     * names make this ordinary: "ë" and "ç" are two bytes each.
     */
    public function test_a_long_multibyte_item_name_still_serializes(): void
    {
        $name = str_repeat('a', 119) . "\u{00eb}" . 'fund';

        $built = $this->builder->build(
            $this->snapshot,
            $this->couponDataWithItemName($name),
            $this->config,
            couponId: 99,
        );

        $encoded = $built->posCoupon->getItems()[0]->getName();

        $this->assertTrue(mb_check_encoding($encoded, 'UTF-8'), 'Item name must stay valid UTF-8.');
        $this->assertLessThanOrEqual(120, strlen($encoded), 'Item name must stay within the 120-byte budget.');
        $this->assertSame(119, strlen($encoded), 'The split character must be dropped whole, not halved.');
        $built->posCoupon->serializeToString();
    }

    public function test_a_short_multibyte_item_name_is_not_truncated(): void
    {
        $name = "Kafe me qum\u{00eb}sht dhe \u{00e7}okollat\u{00eb}";

        $built = $this->builder->build(
            $this->snapshot,
            $this->couponDataWithItemName($name),
            $this->config,
            couponId: 99,
        );

        $this->assertSame($name, $built->posCoupon->getItems()[0]->getName());
    }

    /**
     * A name that is already invalid UTF-8 cannot be rescued by cutting it, so it
     * must be refused with a clear configuration error rather than reaching
     * protobuf and failing there.
     */
    public function test_an_invalid_utf8_item_name_is_rejected(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessage('valid UTF-8');

        $this->builder->build(
            $this->snapshot,
            $this->couponDataWithItemName("Produkt \xFF\xFE"),
            $this->config,
            couponId: 99,
        );
    }

    private function couponDataWithItemName(string $name): CouponData
    {
        return new CouponData(
            items:      [new ItemData($name, 10000, 'cope', 1.0, 100000, 'D')],
            payments:   [new PaymentData(PaymentType::Cash, 1000)],
            operatorId: 'John',
        );
    }

    /**
     * The payload and the caller's record must be two views of one receipt. The
     * builder derives the totals from the items, so if the caller's own figures
     * disagree the two have drifted and the coupon must not be signed: it would
     * contradict the journal entry it was issued from.
     */
    public function test_a_total_that_contradicts_the_callers_record_is_refused(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessage('does not match');

        $this->builder->build(
            $this->snapshot,
            $this->couponDataWithExpectations(expectedTotal: 999, expectedTotalTax: null),
            $this->config,
            couponId: 99,
        );
    }

    public function test_a_tax_total_that_contradicts_the_callers_record_is_refused(): void
    {
        $this->expectException(FiscalConfigurationException::class);
        $this->expectExceptionMessage('tax');

        $this->builder->build(
            $this->snapshot,
            $this->couponDataWithExpectations(expectedTotal: null, expectedTotalTax: 999),
            $this->config,
            couponId: 99,
        );
    }

    public function test_matching_expectations_build_normally(): void
    {
        // 1000 gross at code D (8%) -> 74 tax, checked against the builder's own sums.
        $built = $this->builder->build(
            $this->snapshot,
            $this->couponDataWithExpectations(expectedTotal: 1000, expectedTotalTax: 74),
            $this->config,
            couponId: 99,
        );

        $this->assertSame(1000, $built->posCoupon->getTotal());
        $this->assertSame(74, $built->posCoupon->getTotalTax());
    }

    /** A caller with no separate record of its own is not forced to invent one. */
    public function test_expectations_are_optional(): void
    {
        $built = $this->builder->build($this->snapshot, $this->validCouponData(), $this->config, couponId: 99);

        $this->assertSame(1000, $built->posCoupon->getTotal());
    }

    private function couponDataWithExpectations(?int $expectedTotal, ?int $expectedTotalTax): CouponData
    {
        return new CouponData(
            items:            [new ItemData('Produkt A', 10000, 'cope', 1.0, 100000, 'D')],
            payments:         [new PaymentData(PaymentType::Cash, 1000)],
            operatorId:       'John',
            expectedTotal:    $expectedTotal,
            expectedTotalTax: $expectedTotalTax,
        );
    }

    private function validCouponData(): CouponData
    {
        return new CouponData(
            items:      [new ItemData('Produkt A', 10000, 'cope', 1.0, 100000, 'D')],
            payments:   [new PaymentData(PaymentType::Cash, 1000)],
            operatorId: 'John',
        );
    }
}
