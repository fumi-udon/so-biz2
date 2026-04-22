<?php

namespace App\Support\Pos\Receipt;

use App\Models\OrderLine;
use App\Models\PosOrder;
use Illuminate\Support\Collection;

/**
 * レシート・POS フッター用: PosOrder コレクションを税込明細行（親＋トッピング）へフラット化。
 *
 * @see ReceiptPreview::enrichedLinesForPrint() から抽出（印字・画面で同一ロジック）
 */
final class PosOrderReceiptLineEnricher
{
    /**
     * @param  Collection<int, PosOrder>  $orders
     * @return list<array{kind:string,qty:int,name:string,unit_price_minor:int,amount_minor:int,vat_percent:float}>
     */
    public static function enrich(Collection $orders, ?float $defaultVatPercent = null): array
    {
        $defaultVat = $defaultVatPercent ?? ReceiptTaxMath::defaultVatPercent();
        $out = [];
        foreach ($orders as $order) {
            $lines = $order->relationLoaded('lines')
                ? $order->lines->sortBy('id')
                : OrderLine::query()->where('order_id', $order->id)->orderBy('id')->get();

            foreach ($lines as $line) {
                $lineVatRaw = $line->vat_rate_percent;
                $lineVat = $lineVatRaw === null ? $defaultVat : (float) $lineVatRaw;
                $qty = max(1, (int) $line->qty);
                $unitFull = max(0, (int) $line->unit_price_minor);
                $disc = max(0, (int) ($line->line_discount_minor ?? 0));
                $payload = is_array($line->snapshot_options_payload) ? $line->snapshot_options_payload : [];
                $toppings = is_array($payload['toppings'] ?? null) ? $payload['toppings'] : [];

                $sumDelta = 0;
                foreach ($toppings as $t) {
                    if (is_array($t)) {
                        $sumDelta += max(0, (int) ($t['price_delta_minor'] ?? 0));
                    }
                }

                $baseUnit = max(0, $unitFull - $sumDelta);
                $style = is_array($payload['style'] ?? null) ? $payload['style'] : null;
                $styleName = is_array($style) ? trim((string) ($style['name'] ?? '')) : '';
                $productName = trim((string) $line->snapshot_name);
                $parentDescription = $styleName !== ''
                    ? trim($productName.' '.$styleName)
                    : $productName;

                $parentAmount = max(0, $qty * $baseUnit - $disc);

                $out[] = [
                    'kind' => 'parent',
                    'qty' => $qty,
                    'name' => $parentDescription,
                    'unit_price_minor' => $baseUnit,
                    'amount_minor' => $parentAmount,
                    'vat_percent' => $lineVat,
                ];

                foreach ($toppings as $t) {
                    if (! is_array($t)) {
                        continue;
                    }
                    $delta = max(0, (int) ($t['price_delta_minor'] ?? 0));
                    $tName = trim((string) ($t['name'] ?? ''));

                    $out[] = [
                        'kind' => 'extra',
                        'qty' => $qty,
                        'name' => $tName !== '' ? $tName : '—',
                        'unit_price_minor' => $delta,
                        'amount_minor' => $qty * $delta,
                        'vat_percent' => $lineVat,
                    ];
                }
            }
        }

        return $out;
    }
}
