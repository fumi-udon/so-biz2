<?php

namespace App\Support\Pos\Receipt;

use App\Enums\PrintIntent;
use App\Support\MenuItemMoney;

/**
 * ePOS-Print XML 組み立て（48 桁レイアウト + チュニジア税表示）。
 *
 * @phpstan-type ReceiptPrintPayload array{
 *   shop_name: string,
 *   header_address_lines?: list<string>,
 *   shop_phone?: string|null,
 *   shop_vat_reg?: string|null,
 *   table_no: string,
 *   receipt_number: string,
 *   receipt_date_dmY: string,
 *   receipt_time_his: string,
 *   intent: PrintIntent,
 *   lines: list<array{kind?: string, qty:int, name:string, unit_price_minor:int, amount_minor:int, vat_percent:float|int}>,
 *   order_discount_minor?: int,
 *   rounding_adjustment_minor?: int,
 *   subtotal_ht_minor: int,
 *   total_vat_minor: int,
 *   final_total_minor: int,
 *   vat_buckets: list<array{rate: float, ht_minor: int, vat_minor: int}>,
 *   show_payment_block?: bool,
 *   payment_label?: string|null,
 *   tendered_minor?: int|null,
 *   change_minor?: int|null,
 *   printed_at: string,
 *   duplicate_original_at?: string|null,
 *   footer_thanks_lines?: list<string>,
 * }
 */
final class EpsonReceiptPrintAssembler
{
    /**
     * @param  ReceiptPrintPayload  $payload
     */
    public function assemble(array $payload): string
    {
        $buf = [];
        $buf[] = '<?xml version="1.0" encoding="utf-8"?>';
        $buf[] = '<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">';

        $intent = $payload['intent'];

        if ($intent === PrintIntent::Copy) {
            $buf[] = $this->textLine('DUPLICATA', align: 'center', bold: true);
            $buf[] = $this->feed(1);
        }

        $buf[] = $this->textLine((string) $payload['shop_name'], align: 'center', bold: true);
        $buf[] = $this->feed(1);

        foreach ($payload['header_address_lines'] ?? [] as $addrLine) {
            $line = trim((string) $addrLine);
            if ($line !== '') {
                $buf[] = $this->textLine(ReceiptLineFormatter::centerLine($line), align: 'center');
            }
        }

        $phone = trim((string) ($payload['shop_phone'] ?? ''));
        if ($phone !== '') {
            $buf[] = $this->textLine(ReceiptLineFormatter::centerLine('Tel: '.$phone), align: 'center');
        }

        $vatReg = trim((string) ($payload['shop_vat_reg'] ?? ''));
        if ($vatReg !== '') {
            $buf[] = $this->textLine(ReceiptLineFormatter::centerLine('MF: '.$vatReg), align: 'center');
        }

        $buf[] = $this->feed(1);

        if ($intent === PrintIntent::Addition) {
            $buf[] = $this->textLine('ADDITION / PROFORMA', align: 'center', bold: true);
            $buf[] = $this->feed(1);
        } elseif ($intent === PrintIntent::Receipt || $intent === PrintIntent::Copy) {
            $buf[] = $this->textLine('REÇU / NOTE', align: 'center', bold: true);
            $buf[] = $this->feed(1);
        } elseif ($intent === PrintIntent::StaffCopy) {
            $buf[] = $this->textLine('STAFF COPY', align: 'center', bold: true);
            $buf[] = $this->feed(1);
        }

        $tableNo = (string) ($payload['table_no'] ?? '');
        $buf[] = $this->textLine('TABLE NO: '.$tableNo, align: 'left');
        $buf[] = $this->textLine(
            ReceiptLineFormatter::metaTwoColumnLine(
                'RECEIPT: '.($payload['receipt_number'] ?? ''),
                'DATE: '.($payload['receipt_date_dmY'] ?? ''),
            ),
            align: 'left',
        );
        $buf[] = $this->textLine(
            'TIME: '.($payload['receipt_time_his'] ?? ''),
            align: 'left',
        );

        if (! empty($payload['duplicate_original_at'])) {
            $buf[] = $this->textLine((string) $payload['duplicate_original_at'], align: 'center');
        }

        $buf[] = $this->textLine(ReceiptLineFormatter::separatorLine(), align: 'left');
        $buf[] = $this->textLine(ReceiptLineFormatter::itemColumnHeaderLine(), align: 'left');

        foreach ($payload['lines'] as $line) {
            $kind = (string) ($line['kind'] ?? 'parent');
            if ($kind === 'extra') {
                $rows = ReceiptLineFormatter::formatIndentedExtraRow(
                    (string) $line['name'],
                    (int) $line['unit_price_minor'],
                    (int) $line['amount_minor'],
                );
            } else {
                $rows = ReceiptLineFormatter::formatItemRows(
                    (int) $line['qty'],
                    (string) $line['name'],
                    (int) $line['unit_price_minor'],
                    (int) $line['amount_minor'],
                );
            }
            foreach ($rows as $row) {
                $buf[] = $this->textLine($row, align: 'left');
            }
        }

        $buf[] = $this->textLine(ReceiptLineFormatter::separatorLine(), align: 'left');
        $buf[] = $this->feed(1);

        $buf[] = $this->textLine(
            ReceiptLineFormatter::totalsDtLine('SOUS-TOTAL (HT) :', (int) $payload['subtotal_ht_minor']),
            align: 'left',
        );
        $buf[] = $this->textLine(
            ReceiptLineFormatter::totalsDtLine('TVA :', (int) $payload['total_vat_minor']),
            align: 'left',
        );

        if (! empty($payload['order_discount_minor'])) {
            $disc = abs((int) $payload['order_discount_minor']);
            $buf[] = $this->textLine(
                ReceiptLineFormatter::metaTwoColumnLine(
                    'Remise (TTC) :',
                    '- '.MenuItemMoney::formatMinorForDisplay($disc),
                ),
                align: 'left',
            );
        }
        if (! empty($payload['rounding_adjustment_minor'])) {
            $rnd = abs((int) $payload['rounding_adjustment_minor']);
            $buf[] = $this->textLine(
                ReceiptLineFormatter::metaTwoColumnLine(
                    'Arrondi :',
                    '- '.MenuItemMoney::formatMinorForDisplay($rnd),
                ),
                align: 'left',
            );
        }

        $buf[] = $this->textBlock(
            ReceiptLineFormatter::totalsDtLine('TOTAL (TTC) :', (int) $payload['final_total_minor']),
            bold: true,
            align: 'left',
            heightScale: 2,
        );

        if (! empty($payload['show_payment_block'])) {
            $label = trim((string) ($payload['payment_label'] ?? ''));
            if ($label !== '') {
                $buf[] = $this->textLine(
                    ReceiptLineFormatter::totalsDtLine($label.' :', (int) ($payload['tendered_minor'] ?? 0)),
                    align: 'left',
                );
            }
            if (isset($payload['change_minor']) && (int) $payload['change_minor'] > 0) {
                $buf[] = $this->textLine(
                    ReceiptLineFormatter::totalsDtLine('RENDU :', (int) $payload['change_minor']),
                    align: 'left',
                );
            }
        }

        $buf[] = $this->feed(1);
        $buf[] = $this->textLine(ReceiptLineFormatter::separatorLine(), align: 'left');
        $buf[] = $this->textLine(
            ReceiptLineFormatter::vatBreakdownRow('TVA%', 'HT (NET)', 'TVA'),
            align: 'left',
        );
        $buf[] = $this->textLine(ReceiptLineFormatter::separatorLine(), align: 'left');

        foreach ($payload['vat_buckets'] as $bucket) {
            $rate = number_format((float) $bucket['rate'], 2, '.', '');
            $ht = MenuItemMoney::formatMinorForDisplay((int) $bucket['ht_minor']);
            $vat = MenuItemMoney::formatMinorForDisplay((int) $bucket['vat_minor']);
            $buf[] = $this->textLine(
                ReceiptLineFormatter::vatBreakdownRow($rate, $ht, $vat),
                align: 'left',
            );
        }

        $buf[] = $this->textLine(ReceiptLineFormatter::separatorLine(), align: 'left');
        $buf[] = $this->feed(1);

        foreach ($payload['footer_thanks_lines'] ?? ['Merci de votre visite'] as $ft) {
            $t = trim((string) $ft);
            if ($t !== '') {
                $buf[] = $this->textLine(ReceiptLineFormatter::centerLine($t), align: 'center');
            }
        }

        if ($intent === PrintIntent::Receipt) {
            $buf[] = $this->textLine(ReceiptLineFormatter::centerLine('Au plaisir de vous revoir.'), align: 'center');
        }

        $buf[] = $this->feed(3);
        $buf[] = '<cut type="feed"/>';
        $buf[] = '</epos-print>';

        return implode('', $buf);
    }

    private function textLine(string $text, string $align = 'left', bool $bold = false): string
    {
        return $this->textBlock($text, $bold, $align);
    }

    /**
     * @param  int  $heightScale  縦倍率（1=標準）。2 で倍高のみ（横幅はプリンタ既定のまま）。
     * @param  string|null  $font  e.g. font_b（必要時のみ明示）
     */
    private function textBlock(string $text, bool $bold = false, string $align = 'left', int $heightScale = 1, ?string $font = null): string
    {
        $open = '<text';
        $open .= ' align="'.$align.'"';
        if ($bold) {
            $open .= ' em="true"';
        }
        if ($heightScale > 1) {
            $open .= ' height="'.min(8, $heightScale).'"';
        }
        if ($font !== null && $font !== '') {
            $open .= ' font="'.htmlspecialchars($font, ENT_XML1 | ENT_QUOTES, 'UTF-8').'"';
        }
        $open .= '>';

        return $open.$this->xmlEscape($text).'&#10;</text>';
    }

    private function feed(int $n): string
    {
        return '<feed line="'.max(0, $n).'"/>';
    }

    private function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
