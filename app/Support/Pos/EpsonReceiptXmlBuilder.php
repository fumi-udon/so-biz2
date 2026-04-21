<?php

namespace App\Support\Pos;

use App\Enums\PrintIntent;
use App\Support\MenuItemMoney;

/**
 * Minimal ePOS-Print XML generator for the TM-m30II (58/80mm).
 *
 * Produces the exact XML the browser-side ePOSDevice driver sends over WebSocket.
 * The tags used are the common subset documented in
 * Epson ePOS-Print XML Specification (addText / addFeedLine / addCut).
 *
 * This class is intentionally small, pure, and deterministic: given the same
 * {@see PrintPayload} input it always returns byte-identical XML, which is
 * what the downstream idempotency key hash relies on.
 */
final class EpsonReceiptXmlBuilder
{
    /**
     * @param  array{
     *   shop_name: string,
     *   table_label: string,
     *   intent: PrintIntent,
     *   lines: list<array{qty:int, name:string, amount_minor:int}>,
     *   subtotal_minor: int,
     *   order_discount_minor?: int,
     *   rounding_adjustment_minor?: int,
     *   final_total_minor: int,
     *   tendered_minor?: int,
     *   change_minor?: int,
     *   printed_at: string,
     *   duplicate_original_at?: string
     * }  $payload
     */
    public function build(array $payload): string
    {
        $buf = [];
        $buf[] = '<?xml version="1.0" encoding="utf-8"?>';
        $buf[] = '<epos-print xmlns="http://www.epson-pos.com/schemas/2011/03/epos-print">';

        if ($payload['intent'] === PrintIntent::Copy) {
            $buf[] = $this->textLine('DUPLICATA', align: 'center', bold: true);
            $buf[] = $this->feed(1);
        }

        $buf[] = $this->textBlock($payload['shop_name'], bold: true, doubleSize: true, align: 'center');
        $buf[] = $this->feed(1);
        $buf[] = $this->textLine($this->intentLabel($payload['intent']).' — '.$payload['table_label'], align: 'center');
        $buf[] = $this->textLine($payload['printed_at'], align: 'center');
        if (! empty($payload['duplicate_original_at'])) {
            $buf[] = $this->textLine((string) $payload['duplicate_original_at'], align: 'center');
        }
        $buf[] = $this->feed(1);

        foreach ($payload['lines'] as $line) {
            $buf[] = $this->textLine($this->formatLine((int) $line['qty'], (string) $line['name'], (int) $line['amount_minor']));
        }
        $buf[] = $this->feed(1);

        $buf[] = $this->textLine($this->kvLine('Sous-total', (int) $payload['subtotal_minor']));

        if (! empty($payload['order_discount_minor'])) {
            $buf[] = $this->textLine($this->kvLine('Remise', -1 * (int) $payload['order_discount_minor']));
        }
        if (! empty($payload['rounding_adjustment_minor'])) {
            $buf[] = $this->textLine($this->kvLine('Arrondi', -1 * (int) $payload['rounding_adjustment_minor']));
        }

        $buf[] = $this->textLine($this->kvLine('TOTAL', (int) $payload['final_total_minor']), bold: true);

        if (isset($payload['tendered_minor'])) {
            $buf[] = $this->textLine($this->kvLine('Reçu', (int) $payload['tendered_minor']));
            $buf[] = $this->textLine($this->kvLine('Rendu', (int) ($payload['change_minor'] ?? 0)));
        }

        $buf[] = $this->feed(3);
        $buf[] = '<cut type="feed"/>';
        $buf[] = '</epos-print>';

        return implode('', $buf);
    }

    private function intentLabel(PrintIntent $intent): string
    {
        return match ($intent) {
            PrintIntent::Addition => 'ADDITION',
            PrintIntent::Receipt => 'REÇU',
            PrintIntent::Copy => 'DUPLICATA',
            PrintIntent::StaffCopy => 'STAFF',
        };
    }

    private function textBlock(string $text, bool $bold = false, bool $doubleSize = false, string $align = 'left'): string
    {
        $open = '<text';
        $open .= ' align="'.$align.'"';
        if ($bold) {
            $open .= ' em="true"';
        }
        if ($doubleSize) {
            $open .= ' width="2" height="2"';
        }
        $open .= '>';

        return $open.$this->xmlEscape($text).'&#10;</text>';
    }

    private function textLine(string $text, string $align = 'left', bool $bold = false): string
    {
        return $this->textBlock($text, bold: $bold, doubleSize: false, align: $align);
    }

    private function feed(int $n): string
    {
        return '<feed line="'.max(0, $n).'"/>';
    }

    private function formatLine(int $qty, string $name, int $amountMinor): string
    {
        $left = $qty.' '.$name;
        $right = MenuItemMoney::formatMinorForDisplay(max(0, $amountMinor));

        return $this->padBetween($left, $right, 32);
    }

    private function kvLine(string $label, int $amountMinor): string
    {
        $value = ($amountMinor < 0 ? '-' : '').MenuItemMoney::formatMinorForDisplay(abs($amountMinor));

        return $this->padBetween($label, $value, 32);
    }

    private function padBetween(string $left, string $right, int $cols): string
    {
        $left = mb_substr($left, 0, $cols - mb_strlen($right) - 1);
        $spaces = max(1, $cols - mb_strlen($left) - mb_strlen($right));

        return $left.str_repeat(' ', $spaces).$right;
    }

    private function xmlEscape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
