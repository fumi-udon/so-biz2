<?php

namespace Tests\Unit\Support\Pos\Receipt;

use App\Support\Pos\Receipt\ReceiptLineFormatter;
use PHPUnit\Framework\TestCase;

final class ReceiptLineFormatterTest extends TestCase
{
    public function test_item_row_is_exactly_48_columns(): void
    {
        $rows = ReceiptLineFormatter::formatItemRows(
            2,
            'Couscous',
            12_000,
            24_000,
        );
        $this->assertCount(1, $rows);
        $this->assertSame(48, mb_strwidth($rows[0], 'UTF-8'));
    }

    public function test_item_wraps_long_description(): void
    {
        $long = str_repeat('あ', 25);
        $rows = ReceiptLineFormatter::formatItemRows(1, $long, 5_000, 5_000);
        $this->assertGreaterThan(1, count($rows));
        foreach ($rows as $r) {
            $this->assertSame(48, mb_strwidth($r, 'UTF-8'));
        }
    }

    public function test_separator_is_48_hyphens(): void
    {
        $this->assertSame(48, strlen(ReceiptLineFormatter::separatorLine()));
    }
}
