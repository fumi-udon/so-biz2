<?php

namespace Tests\Unit\Support;

use App\Support\MenuItemMoney;
use PHPUnit\Framework\TestCase;

class MenuItemMoneyTest extends TestCase
{
    public function test_parse_and_display_half_dt(): void
    {
        $this->assertSame(0, MenuItemMoney::parseDtInputToMinor(''));
        $this->assertSame(0, MenuItemMoney::parseDtInputToMinor('0'));
        $this->assertSame(12000, MenuItemMoney::parseDtInputToMinor('12'));
        $this->assertSame(12500, MenuItemMoney::parseDtInputToMinor('12.5'));
        $this->assertSame(12500, MenuItemMoney::parseDtInputToMinor('12,5'));
        $this->assertSame(12500, MenuItemMoney::parseDtInputToMinor(' 12.5dt '));
        $this->assertSame(500, MenuItemMoney::parseDtInputToMinor('0.5'));

        $this->assertSame('12', MenuItemMoney::minorToDtInputString(12000));
        $this->assertSame('12.5', MenuItemMoney::minorToDtInputString(12500));

        $this->assertSame('12 DT', MenuItemMoney::formatMinorForDisplay(12000));
        $this->assertSame('12.5 DT', MenuItemMoney::formatMinorForDisplay(12500));
        $this->assertSame('0.5 DT', MenuItemMoney::formatMinorForDisplay(500));
    }

    public function test_snaps_to_half_dt(): void
    {
        $this->assertSame(12000, MenuItemMoney::snapMinorToHalfDt(12001));
        $this->assertSame(12500, MenuItemMoney::snapMinorToHalfDt(12300));
    }

    public function test_normalize_persisted_option_minor_avoids_double_dt_parse_on_int(): void
    {
        $this->assertSame(14000, MenuItemMoney::normalizePersistedOptionMinor(14000));
        $this->assertSame(14000, MenuItemMoney::normalizePersistedOptionMinor('14'));
        $this->assertSame(12500, MenuItemMoney::normalizePersistedOptionMinor('12.5'));
    }
}
