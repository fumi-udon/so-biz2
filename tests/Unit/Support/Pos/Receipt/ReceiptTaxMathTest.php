<?php

namespace Tests\Unit\Support\Pos\Receipt;

use App\Support\Pos\Receipt\ReceiptTaxMath;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ReceiptTaxMathTest extends TestCase
{
    public function test_default_vat_percent_reads_config(): void
    {
        Config::set('pos.receipt.default_tva_rate', 13.0);

        $this->assertSame(13.0, ReceiptTaxMath::defaultVatPercent());
    }

    public function test_format_percent_for_ui(): void
    {
        $this->assertSame('13', ReceiptTaxMath::formatPercentForUi(13.0));
        $this->assertSame('19.5', ReceiptTaxMath::formatPercentForUi(19.5));
    }

    public function test_split_ttc_minor_rounds_ht_and_reconciles_vat(): void
    {
        $split = ReceiptTaxMath::splitTtcMinor(1_000, 13.0);

        $this->assertSame(885, $split['ht_minor']);
        $this->assertSame(115, $split['vat_minor']);
        $this->assertSame(1_000, $split['ht_minor'] + $split['vat_minor']);
    }

    public function test_aggregate_merges_same_rate_and_missing_vat_uses_config_default(): void
    {
        Config::set('pos.receipt.default_tva_rate', 13.0);

        $merged = ReceiptTaxMath::aggregateVatBuckets([
            ['ttc_minor' => 1_000, 'vat_percent' => 13.0],
            ['ttc_minor' => 500, 'vat_percent' => 13.0],
        ]);
        $this->assertCount(1, $merged);
        $this->assertSame(13.0, $merged[0]['rate'] ?? null);

        $fromMissing = ReceiptTaxMath::aggregateVatBuckets([['ttc_minor' => 1_200]]);
        $this->assertCount(1, $fromMissing);
        $this->assertSame(13.0, $fromMissing[0]['rate'] ?? null);
    }
}
