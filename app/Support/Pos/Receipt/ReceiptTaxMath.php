<?php

namespace App\Support\Pos\Receipt;

/**
 * チュニジア POS 想定: 明細金額は TTC（税込）として格納され、税率で HT / TVA を分割する。
 */
final class ReceiptTaxMath
{
    /**
     * 店舗既定の TVA 率（%）。`.env` の `TVA_TN` を優先し、未設定時は `POS_RECEIPT_VAT_DEFAULT`。
     */
    public static function defaultVatPercent(): float
    {
        $v = config('pos.receipt.default_tva_rate');
        if ($v !== null) {
            return (float) $v;
        }

        return (float) config('pos.receipt.default_vat_percent', 19.0);
    }

    /** UI / 翻訳プレースホルダ用（例: 13, 19.5） */
    public static function formatPercentForUi(float $rate): string
    {
        if (abs($rate - round($rate)) < 0.001) {
            return (string) (int) round($rate);
        }

        return rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
    }

    /**
     * TTC ミリウムから HT と TVA（ミリウム）を算出（税込価格前提）。
     *
     * @return array{ht_minor: int, vat_minor: int}
     */
    public static function splitTtcMinor(int $ttcMinor, float $vatPercent): array
    {
        $ttcMinor = max(0, $ttcMinor);
        if ($ttcMinor === 0 || $vatPercent <= 0.0) {
            return ['ht_minor' => 0, 'vat_minor' => 0];
        }

        $divisor = 1.0 + ($vatPercent / 100.0);
        $ht = (int) round($ttcMinor / $divisor);
        $vat = $ttcMinor - $ht;

        return ['ht_minor' => max(0, $ht), 'vat_minor' => max(0, $vat)];
    }

    /**
     * @param  list<array{ttc_minor: int, vat_percent: float}>  $lines
     * @return array<int, array{rate: float, ht_minor: int, vat_minor: int}>
     */
    public static function aggregateVatBuckets(array $lines): array
    {
        /** @var array<string, array{rate: float, ht_minor: int, vat_minor: int}> $buckets */
        $buckets = [];
        foreach ($lines as $row) {
            $rate = round((float) ($row['vat_percent'] ?? self::defaultVatPercent()), 2);
            $key = (string) $rate;
            $split = self::splitTtcMinor((int) ($row['ttc_minor'] ?? 0), $rate);
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['rate' => $rate, 'ht_minor' => 0, 'vat_minor' => 0];
            }
            $buckets[$key]['ht_minor'] += $split['ht_minor'];
            $buckets[$key]['vat_minor'] += $split['vat_minor'];
        }

        krsort($buckets, SORT_NUMERIC);

        return array_values($buckets);
    }

    /**
     * バケット合計の HT / TVA（明細合算）
     *
     * @param  array<int, array{rate: float, ht_minor: int, vat_minor: int}>  $buckets
     * @return array{ht_minor: int, vat_minor: int}
     */
    public static function sumBucketsHtVat(array $buckets): array
    {
        $h = 0;
        $v = 0;
        foreach ($buckets as $b) {
            $h += (int) ($b['ht_minor'] ?? 0);
            $v += (int) ($b['vat_minor'] ?? 0);
        }

        return ['ht_minor' => $h, 'vat_minor' => $v];
    }
}
