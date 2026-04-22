<?php

namespace Tests\Unit\Support\Pos\Receipt;

use App\Support\Pos\Receipt\ReceiptLineFormatter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReceiptLineFormatter::class)]
final class ReceiptLineFormatterIndentedExtraTest extends TestCase
{
    public function test_indented_extra_single_row_is_48_columns_wide(): void
    {
        $rows = ReceiptLineFormatter::formatIndentedExtraRow('wakame', 3_000, 6_000);
        $this->assertCount(1, $rows);
        $this->assertSame(48, mb_strwidth($rows[0], 'UTF-8'));
        $this->assertStringContainsString('extra:', $rows[0]);
    }

    public function test_indented_extra_zero_shows_zero_amount(): void
    {
        $rows = ReceiptLineFormatter::formatIndentedExtraRow('free', 0, 0);
        $this->assertCount(1, $rows);
        $this->assertSame(48, mb_strwidth($rows[0], 'UTF-8'));
        $this->assertStringContainsString('0.000', $rows[0]);
    }
}
