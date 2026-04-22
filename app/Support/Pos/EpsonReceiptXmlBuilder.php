<?php

namespace App\Support\Pos;

use App\Enums\PrintIntent;
use App\Support\Pos\Receipt\EpsonReceiptPrintAssembler;
use App\Support\Pos\Receipt\ReceiptTaxMath;
use Illuminate\Support\Carbon;

/**
 * POS レシート ePOS-Print XML — {@see EpsonReceiptPrintAssembler} へのファサード。
 *
 * 後方互換: 従来の簡易 payload（明細に unit_price / vat なし）も {@see normalizePayload} で正規化。
 */
final class EpsonReceiptXmlBuilder
{
    public function build(array $payload): string
    {
        $normalized = $this->normalizePayload($payload);

        return (new EpsonReceiptPrintAssembler)->assemble($normalized);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $intent = $payload['intent'] instanceof PrintIntent
            ? $payload['intent']
            : PrintIntent::tryFrom((string) ($payload['intent'] ?? 'addition')) ?? PrintIntent::Addition;

        $printedAt = (string) ($payload['printed_at'] ?? now()->format('Y-m-d H:i'));
        $dt = Carbon::parse($printedAt);

        $defaultVat = ReceiptTaxMath::defaultVatPercent();

        $lines = [];
        foreach ($payload['lines'] ?? [] as $row) {
            $qty = max(1, (int) ($row['qty'] ?? 1));
            $name = (string) ($row['name'] ?? '');
            $amount = (int) ($row['amount_minor'] ?? 0);
            $unit = isset($row['unit_price_minor']) ? (int) $row['unit_price_minor'] : intdiv($amount, $qty);
            $vatP = isset($row['vat_percent']) ? (float) $row['vat_percent'] : $defaultVat;
            $lines[] = [
                'kind' => (string) ($row['kind'] ?? 'parent'),
                'qty' => $qty,
                'name' => $name,
                'unit_price_minor' => $unit,
                'amount_minor' => $amount,
                'vat_percent' => $vatP,
            ];
        }

        $bucketInput = [];
        foreach ($lines as $ln) {
            $bucketInput[] = [
                'ttc_minor' => (int) $ln['amount_minor'],
                'vat_percent' => (float) $ln['vat_percent'],
            ];
        }
        $vatBuckets = ReceiptTaxMath::aggregateVatBuckets($bucketInput);
        $sumHv = ReceiptTaxMath::sumBucketsHtVat($vatBuckets);

        $subtotalHt = (int) ($payload['subtotal_ht_minor'] ?? $sumHv['ht_minor']);
        $totalVat = (int) ($payload['total_vat_minor'] ?? $sumHv['vat_minor']);

        $finalTotal = (int) ($payload['final_total_minor'] ?? $payload['subtotal_minor'] ?? 0);

        $brand = trim((string) config('pos.receipt.brand_name', ''));
        $shopName = $brand !== '' ? $brand : (string) ($payload['shop_name'] ?? '');

        $epsonAddrRaw = trim((string) config('pos.receipt.epson_address', ''));
        if ($epsonAddrRaw !== '') {
            $addressLines = array_values(array_filter(array_map('trim', explode("\n", $epsonAddrRaw))));
        } else {
            $addressLines = $payload['header_address_lines'] ?? config('pos.receipt.address_lines', []);
        }
        if (! is_array($addressLines)) {
            $addressLines = [];
        }
        $addressLines = array_values(array_filter(array_map('strval', $addressLines)));

        return [
            'shop_name' => $shopName,
            'header_address_lines' => $addressLines,
            'shop_phone' => $this->resolveReceiptShopPhone($payload),
            'shop_vat_reg' => $payload['shop_vat_reg'] ?? config('pos.receipt.mf_number'),
            'table_no' => (string) ($payload['table_no'] ?? $payload['table_label'] ?? ''),
            'receipt_number' => (string) ($payload['receipt_number'] ?? $payload['receipt_ref'] ?? '—'),
            'receipt_date_dmY' => (string) ($payload['receipt_date_dmY'] ?? $dt->format('d/m/Y')),
            'receipt_time_his' => (string) ($payload['receipt_time_his'] ?? $dt->format('H:i:s')),
            'intent' => $intent,
            'lines' => $lines,
            'order_discount_minor' => (int) ($payload['order_discount_minor'] ?? 0),
            'rounding_adjustment_minor' => (int) ($payload['rounding_adjustment_minor'] ?? 0),
            'subtotal_ht_minor' => $subtotalHt,
            'total_vat_minor' => $totalVat,
            'final_total_minor' => $finalTotal,
            'vat_buckets' => $vatBuckets,
            'show_payment_block' => false,
            'payment_label' => $payload['payment_label'] ?? null,
            'tendered_minor' => $payload['tendered_minor'] ?? null,
            'change_minor' => $payload['change_minor'] ?? null,
            'printed_at' => $printedAt,
            'duplicate_original_at' => $payload['duplicate_original_at'] ?? null,
            'footer_thanks_lines' => $payload['footer_thanks_lines'] ?? config('pos.receipt.footer_thanks_lines', ['Merci de votre visite']),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveReceiptShopPhone(array $payload): string
    {
        $epson = trim((string) config('pos.receipt.epson_tel', ''));
        if ($epson !== '') {
            return $epson;
        }

        $fromPayload = $payload['shop_phone'] ?? null;
        if ($fromPayload !== null && trim((string) $fromPayload) !== '') {
            return trim((string) $fromPayload);
        }

        return trim((string) config('pos.receipt.shop_phone', ''));
    }
}
