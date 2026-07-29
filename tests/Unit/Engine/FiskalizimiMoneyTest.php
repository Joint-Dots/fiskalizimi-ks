<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use Jointdots\FiskalizimiKs\Engine\FiskalizimiMoney;
use PHPUnit\Framework\TestCase;

class FiskalizimiMoneyTest extends TestCase
{
    public function test_to_fiscal_units_converts_euros_to_cents(): void
    {
        $this->assertSame(100, FiskalizimiMoney::toFiscalUnits(1.00));
        $this->assertSame(1850, FiskalizimiMoney::toFiscalUnits(18.50));
        $this->assertSame(0, FiskalizimiMoney::toFiscalUnits(0));
    }

    public function test_to_fiscal_units_rounds_half_up(): void
    {
        $this->assertSame(100, FiskalizimiMoney::toFiscalUnits(0.995));
        $this->assertSame(101, FiskalizimiMoney::toFiscalUnits(1.005));
    }

    /**
     * A float holds 1.005 as 1.00499999999999989, so scaling it by binary
     * arithmetic lands under the half-way mark. PHP 8.3 and earlier rounded that
     * up anyway, PHP 8.4 rounds it down, and a coupon may not carry different
     * cents on either side of that release.
     */
    public function test_to_fiscal_units_rounds_a_half_the_same_way_on_every_php_version(): void
    {
        $this->assertSame(101, FiskalizimiMoney::toFiscalUnits(1.005));
        $this->assertSame(101, FiskalizimiMoney::toFiscalUnits('1.005'));
        $this->assertSame(-101, FiskalizimiMoney::toFiscalUnits(-1.005));
        $this->assertSame(1234, FiskalizimiMoney::toFiscalUnits(12.335));
        $this->assertSame(285, FiskalizimiMoney::toFiscalUnits(2.845));
    }

    public function test_to_fiscal_units_keeps_digits_below_the_half_down(): void
    {
        $this->assertSame(100, FiskalizimiMoney::toFiscalUnits(1.0049));
        $this->assertSame(100, FiskalizimiMoney::toFiscalUnits('1.00499999'));
        $this->assertSame(13, FiskalizimiMoney::toFiscalUnits(0.12999));
    }

    public function test_to_item_price_units_rounds_a_half_up_at_its_own_scale(): void
    {
        $this->assertSame(12346, FiskalizimiMoney::toItemPriceUnits(1.23455));
        $this->assertSame(12345, FiskalizimiMoney::toItemPriceUnits('1.234549'));
        $this->assertSame(-12346, FiskalizimiMoney::toItemPriceUnits(-1.23455));
    }

    public function test_to_item_price_units_converts_euros_to_ten_thousandths(): void
    {
        $this->assertSame(10000, FiskalizimiMoney::toItemPriceUnits(1.00));
        $this->assertSame(18500, FiskalizimiMoney::toItemPriceUnits(1.85));
        $this->assertSame(12345, FiskalizimiMoney::toItemPriceUnits(1.2345));
    }

    public function test_from_fiscal_units_converts_cents_to_euros(): void
    {
        $this->assertSame(1.00, FiskalizimiMoney::fromFiscalUnits(100));
        $this->assertSame(18.50, FiskalizimiMoney::fromFiscalUnits(1850));
    }
}
