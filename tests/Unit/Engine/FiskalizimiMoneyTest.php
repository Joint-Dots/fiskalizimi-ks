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
