<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_money_conversion_uses_decimal_rounding(): void
    {
        $this->assertSame(101, Money::toMinor('1.005'));
        $this->assertSame(-101, Money::toMinor('-1.005'));
        $this->assertSame('1.01', Money::fromMinor(101));
    }

    public function test_percentage_math_handles_large_decimal_values_without_floats(): void
    {
        $this->assertSame(999999899999990, Money::percentage(99999999999999, '999.9999'));
        $this->assertSame(21000000, Money::percentage(200000000, '10.5000'));
    }
}
