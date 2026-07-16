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

    public function build(
        CouponSnapshot $snapshot,
        CouponData     $data,
        FiscalConfig   $config,
        int            $couponId,
    ): BuiltCoupon {
        $this->validateInput($snapshot, $data, $config, $couponId);
        $this->validatePayments($data);

        [$items, $taxGroupTotals, $totalUnits, $totalTaxUnits] = $this->processItems($data->items);
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
        }

        $itemTotal = (int) array_sum(array_map(fn(ItemData $item) => $item->total, $data->items));

        if ($data->totalDiscount > $itemTotal) {
            throw new FiscalConfigurationException('Total discount cannot exceed the coupon total.');
        }

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
        $itemTotal    = (int) array_sum(array_map(fn(ItemData $i) => $i->total, $data->items));

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
            $ci = new CouponItem();
            $ci->setName(substr($item->name, 0, 120));
            $ci->setPrice($item->price);
            $ci->setUnit($item->unit);
            $ci->setQuantity($item->quantity);
            $ci->setTotal($item->total);
            $ci->setTaxRate($item->taxRate);
            $ci->setType($item->type);
            $couponItems[] = $ci;

            $rate     = self::TAX_RATES[$item->taxRate] ?? 0.0;
            $taxUnits = $rate > 0.0
                ? (int) round($item->total - ($item->total / (1 + $rate)), 0, PHP_ROUND_HALF_UP)
                : 0;

            $taxGroupTotals[$item->taxRate] ??= ['total_for_tax' => 0, 'total_tax' => 0];
            $taxGroupTotals[$item->taxRate]['total_tax']     += $taxUnits;
            $taxGroupTotals[$item->taxRate]['total_for_tax'] += $item->total - $taxUnits;

            $totalUnits    += $item->total;
            $totalTaxUnits += $taxUnits;
        }

        ksort($taxGroupTotals);

        return [$couponItems, $taxGroupTotals, $totalUnits, $totalTaxUnits];
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
