<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\CouponType;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Generated\CitizenCoupon;
use Jointdots\FiskalizimiKs\Generated\CouponItem;
use Jointdots\FiskalizimiKs\Generated\CouponType as ProtoCouponType;
use Jointdots\FiskalizimiKs\Generated\Payment;
use Jointdots\FiskalizimiKs\Generated\PaymentType as ProtoPaymentType;
use Jointdots\FiskalizimiKs\Generated\PosCoupon;
use Jointdots\FiskalizimiKs\Generated\TaxGroup;

final class CouponBuilder
{
    private const TAX_RATES = ['A' => 0.0, 'C' => 0.0, 'D' => 0.08, 'E' => 0.18];

    /**
     * ATK's item-name budget, applied in bytes. The cut uses mb_strcut rather
     * than substr so it lands on a character boundary: a halved UTF-8 sequence
     * makes protobuf refuse the whole payload, and the sale then fails
     * identically on every retry. Albanian names reach this readily.
     */
    private const NAME_MAX_BYTES = 120;

    public function build(
        CouponSnapshot $snapshot,
        CouponData     $data,
        FiscalConfig   $config,
        int            $couponId,
    ): BuiltCoupon {
        $this->validateInput($snapshot, $data, $config, $couponId);
        $this->validatePayments($data);

        [$items, $taxGroupTotals, $totalUnits, $totalTaxUnits] = $this->processItems($data->items);
        $this->assertAgreesWithCallersRecord($data, $totalUnits, $totalTaxUnits);
        $taxGroups  = $this->buildTaxGroups($taxGroupTotals);
        $payments   = $this->buildPayments($data->payments);
        $couponType = $this->mapCouponType($data->type);

        $pos     = new PosCoupon();
        $citizen = new CitizenCoupon();

        // The POS and citizen coupons are two encodings of one receipt, and ATK
        // verifies the citizen QR against the submitted POS payload. Stamping
        // the shared fields from one place is what keeps them consistent — set
        // them per-message and a future edit can update one and miss the other.
        foreach ([$pos, $citizen] as $coupon) {
            $this->stampShared(
                $coupon,
                $config,
                $snapshot,
                $couponId,
                $couponType,
                $taxGroups,
                $totalUnits,
                $totalTaxUnits,
            );
        }

        $pos->setLocation($config->location);
        $pos->setOperatorId($data->operatorId);
        $pos->setApplicationId($config->applicationId);
        $pos->setItems($items);
        $pos->setPayments($payments);
        $pos->setTotalDiscount($data->totalDiscount);

        if ($data->referenceNo !== null) {
            $pos->setReferenceNo($data->referenceNo);
        }

        return new BuiltCoupon($pos, $citizen);
    }

    /**
     * @param TaxGroup[] $taxGroups
     */
    private function stampShared(
        PosCoupon|CitizenCoupon $coupon,
        FiscalConfig            $config,
        CouponSnapshot          $snapshot,
        int                     $couponId,
        int                     $couponType,
        array                   $taxGroups,
        int                     $totalUnits,
        int                     $totalTaxUnits,
    ): void {
        $coupon->setBusinessId($config->businessId);
        $coupon->setCouponId($couponId);
        $coupon->setBranchId($config->branchId);
        $coupon->setPosId($config->posId);
        $coupon->setVerificationNo($snapshot->verificationNo);
        $coupon->setType($couponType);
        $coupon->setTime($snapshot->time);
        $coupon->setTotal($totalUnits);
        $coupon->setTaxGroups($taxGroups);
        $coupon->setTotalTax($totalTaxUnits);
        $coupon->setTotalNoTax($totalUnits - $totalTaxUnits);
    }

    /**
     * The signed payload must agree with the record the caller issued it from.
     * The builder derives the totals from the items, so a caller whose own
     * figures differ has drifted from its journal — and a coupon that
     * contradicts its own journal entry must not be signed.
     *
     * Tax is worth checking separately from the gross: the two are computed by
     * different routes (the caller per stored line, the builder per item here),
     * so they can disagree while the gross still matches.
     */
    private function assertAgreesWithCallersRecord(CouponData $data, int $totalUnits, int $totalTaxUnits): void
    {
        if ($data->expectedTotal !== null && $data->expectedTotal !== $totalUnits) {
            throw new FiscalConfigurationException(
                "Coupon total ({$totalUnits}) does not match the caller's record ({$data->expectedTotal})."
            );
        }

        if ($data->expectedTotalTax !== null && $data->expectedTotalTax !== $totalTaxUnits) {
            throw new FiscalConfigurationException(
                "Coupon tax total ({$totalTaxUnits}) does not match the caller's record ({$data->expectedTotalTax})."
            );
        }
    }

    private function validateInput(
        CouponSnapshot $snapshot,
        CouponData $data,
        FiscalConfig $config,
        int $couponId,
    ): void {
        if ($couponId < 1) {
            throw new FiscalConfigurationException('Coupon ID must be a positive integer.');
        }

        VerificationNo::assertValid($snapshot->verificationNo);

        if ($snapshot->time < 1) {
            throw new FiscalConfigurationException('Fiscal time must be a positive Unix timestamp.');
        }

        foreach ([
            'business ID' => $config->businessId,
            'application ID' => $config->applicationId,
            'POS ID' => $config->posId,
            'branch ID' => $config->branchId,
        ] as $label => $value) {
            if ($value < 1) {
                throw new FiscalConfigurationException(ucfirst($label) . ' must be a positive integer.');
            }
        }

        if (trim($config->location) === '') {
            throw new FiscalConfigurationException('Fiscal location is required.');
        }

        if (strtolower((string) parse_url($config->atkBaseUrl, PHP_URL_SCHEME)) !== 'https') {
            throw new FiscalConfigurationException('ATK base URL must use HTTPS.');
        }

        if ($config->atkTimeout < 1) {
            throw new FiscalConfigurationException('ATK timeout must be at least one second.');
        }

        if (trim($data->operatorId) === '') {
            throw new FiscalConfigurationException('Operator ID is required.');
        }

        if ($data->items === []) {
            throw new FiscalConfigurationException('At least one coupon item is required.');
        }

        if ($data->payments === []) {
            throw new FiscalConfigurationException('At least one payment is required.');
        }

        if ($data->type === CouponType::Sale && $data->referenceNo !== null) {
            throw new FiscalConfigurationException('Sale coupons must not contain a reference number.');
        }

        if ($data->type !== CouponType::Sale && ($data->referenceNo === null || $data->referenceNo < 1)) {
            throw new FiscalConfigurationException('Return and cancel coupons require a positive reference number.');
        }

        if ($data->totalDiscount < 0) {
            throw new FiscalConfigurationException('Total discount cannot be negative.');
        }

        foreach ($data->items as $item) {
            if (!$item instanceof ItemData) {
                throw new FiscalConfigurationException('Coupon items must be ItemData instances.');
            }

            if (!array_key_exists($item->taxRate, self::TAX_RATES)) {
                throw new FiscalConfigurationException("Unsupported ATK tax code: {$item->taxRate}.");
            }

            if ($item->quantity <= 0 || $item->price < 0 || $item->total < 0) {
                throw new FiscalConfigurationException('Item quantity must be positive and monetary values cannot be negative.');
            }

            // Protobuf string fields reject non-UTF-8 bytes from inside
            // serialization, which would surface as an opaque encoding error
            // long after the caller could act on it.
            if (!mb_check_encoding($item->name, 'UTF-8')) {
                throw new FiscalConfigurationException('Coupon item names must be valid UTF-8.');
            }
        }

        // There is deliberately no upper bound. An item's Total is what is left
        // after its markdown, so the discount and the total are disjoint amounts
        // whose sum is the pre-discount subtotal — a discount can never exceed
        // that, and no comparison against the total means anything. Capping it at
        // the total rejected every coupon marked down by more than half, where
        // the money taken off is by definition larger than the money left.

        foreach ($data->payments as $payment) {
            if (!$payment instanceof PaymentData) {
                throw new FiscalConfigurationException('Payments must be PaymentData instances.');
            }

            if ($payment->amount <= 0) {
                throw new FiscalConfigurationException('Payment amounts must be positive integers.');
            }
        }
    }

    private function validatePayments(CouponData $data): void
    {
        $paymentTotal = (int) array_sum(array_map(fn(PaymentData $p) => $p->amount, $data->payments));
        $itemTotal    = $this->itemTotalInFiscalUnits($data->items);

        if ($paymentTotal !== $itemTotal) {
            throw new FiscalConfigurationException(
                "Payment total ({$paymentTotal}) does not match item total ({$itemTotal})."
            );
        }
    }

    private function processItems(array $items): array
    {
        $couponItems    = [];
        $taxGroupTotals = [];
        $totalUnits     = 0;
        $totalTaxUnits  = 0;

        foreach ($items as $item) {
            // The wire keeps the item's own units; everything the coupon totals
            // are built from is the fiscal-unit view of the same amount.
            $itemUnits = FiskalizimiMoney::itemUnitsToFiscalUnits($item->total);

            $ci = new CouponItem();
            $ci->setName(mb_strcut($item->name, 0, self::NAME_MAX_BYTES, 'UTF-8'));
            $ci->setPrice($item->price);
            $ci->setUnit($item->unit);
            $ci->setQuantity($item->quantity);
            $ci->setTotal($item->total);
            $ci->setTaxRate($item->taxRate);
            $ci->setType($item->type);
            $couponItems[] = $ci;

            $rate     = self::TAX_RATES[$item->taxRate] ?? 0.0;
            $taxUnits = $rate > 0.0
                ? (int) round($itemUnits - ($itemUnits / (1 + $rate)), 0, PHP_ROUND_HALF_UP)
                : 0;

            $taxGroupTotals[$item->taxRate] ??= ['total_for_tax' => 0, 'total_tax' => 0];
            $taxGroupTotals[$item->taxRate]['total_tax']     += $taxUnits;
            $taxGroupTotals[$item->taxRate]['total_for_tax'] += $itemUnits - $taxUnits;

            $totalUnits    += $itemUnits;
            $totalTaxUnits += $taxUnits;
        }

        ksort($taxGroupTotals);

        return [$couponItems, $taxGroupTotals, $totalUnits, $totalTaxUnits];
    }

    /**
     * The coupon total as the items make it, in fiscal units. Each item is
     * converted before it is summed, not after, so this is the same figure
     * processItems() reaches — a coupon can never be rejected for a total the
     * builder itself would have produced.
     *
     * @param ItemData[] $items
     */
    private function itemTotalInFiscalUnits(array $items): int
    {
        $total = 0;

        foreach ($items as $item) {
            $total += FiskalizimiMoney::itemUnitsToFiscalUnits($item->total);
        }

        return $total;
    }

    private function buildTaxGroups(array $taxGroupTotals): array
    {
        return array_map(function (string $taxCode, array $totals) {
            $tg = new TaxGroup();
            $tg->setTaxRate($taxCode);
            $tg->setTotalForTax($totals['total_for_tax']);
            $tg->setTotalTax($totals['total_tax']);
            return $tg;
        }, array_keys($taxGroupTotals), $taxGroupTotals);
    }

    private function buildPayments(array $payments): array
    {
        return array_map(function (PaymentData $p) {
            $payment = new Payment();
            $payment->setType($this->mapPaymentType($p->type));
            $payment->setAmount($p->amount);
            return $payment;
        }, $payments);
    }

    private function mapCouponType(CouponType $type): int
    {
        return match ($type) {
            CouponType::Sale   => ProtoCouponType::Sale,
            CouponType::Return => ProtoCouponType::PBReturn,
            CouponType::Cancel => ProtoCouponType::Cancel,
        };
    }

    private function mapPaymentType(PaymentType $type): int
    {
        return match ($type) {
            PaymentType::Cash           => ProtoPaymentType::Cash,
            PaymentType::CreditCard     => ProtoPaymentType::CreditCard,
            PaymentType::Voucher        => ProtoPaymentType::Voucher,
            PaymentType::Cheque         => ProtoPaymentType::Cheque,
            PaymentType::CryptoCurrency => ProtoPaymentType::CryptoCurrency,
            PaymentType::Other          => ProtoPaymentType::Other,
        };
    }
}
