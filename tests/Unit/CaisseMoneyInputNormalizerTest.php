<?php

namespace Tests\Unit;

use App\Support\CaisseMoneyInputNormalizer;
use PHPUnit\Framework\TestCase;

class CaisseMoneyInputNormalizerTest extends TestCase
{
    public function test_empty_returns_null(): void
    {
        $this->assertNull(CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal(null));
        $this->assertNull(CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal(''));
        $this->assertNull(CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal('   '));
    }

    public function test_comma_decimal_and_thousands_dot(): void
    {
        $this->assertSame('1234.5', CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal('1.234,5'));
    }

    public function test_space_thousands_and_comma_decimal(): void
    {
        $this->assertSame('1234.6', CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal('1 234,56'));
    }

    public function test_dot_decimal(): void
    {
        $this->assertSame('12.3', CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal('12.34'));
    }

    public function test_integer_string(): void
    {
        $this->assertSame('0', CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal('0'));
        $this->assertSame('99', CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal(99));
    }

    public function test_float_input(): void
    {
        $this->assertSame('10.5', CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal(10.54));
    }
}
