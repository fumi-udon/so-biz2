<?php

namespace App\Support\Pos\Receipt;

use App\Support\MenuItemMoney;

/**
 * Epson TM-m30 Font A: 半角 48 桁幅（mb_strwidth）で物理段組み。
 *
 * 明細列: QTY(3) | DESC(25) | P.U(7) | AMOUNT(8) + 列間スペース計 5 = 48（VAT 列なし）
 */
final class ReceiptLineFormatter
{
    public const LINE_WIDTH = 48;

    private const W_QTY = 3;

    private const W_DESC = 25;

    private const W_PU = 7;

    private const W_AMT = 8;

    /** 列間スペース（合計 5） */
    private const GAP_QTY_DESC = 2;

    private const GAP_DESC_PU = 2;

    private const GAP_PU_AMT = 1;

    /**
     * 明細ヘッダー行（48 桁）
     */
    public static function itemColumnHeaderLine(): string
    {
        return self::buildItemRow(
            'QTY',
            'DESCRIPTION',
            'P.U',
            'AMOUNT',
            padNumeric: false,
        );
    }

    /**
     * 金額は列内右寄せ（数値のみ、DT 省略で幅収まりを優先）
     *
     * @return list<string> 1 行以上（説明折返し時は複数行）
     */
    public static function formatItemRows(
        int $qty,
        string $description,
        int $unitPriceMinor,
        int $lineAmountMinor,
    ): array {
        $pu = self::formatAmountCompact($unitPriceMinor, self::W_PU);
        $amt = self::formatAmountCompact($lineAmountMinor, self::W_AMT);

        $descLines = self::wrapTextToWidth($description, self::W_DESC);
        $out = [];
        $first = true;
        foreach ($descLines as $chunk) {
            if ($first) {
                $out[] = self::buildItemRow(
                    (string) $qty,
                    $chunk,
                    $pu,
                    $amt,
                    padNumeric: true,
                );
                $first = false;
            } else {
                $out[] = self::buildItemContinuationRow($chunk);
            }
        }

        return $out;
    }

    /**
     * トッピング等のエクストラ行（QTY 列は空白、DESC は「  - extra: 」＋名称、金額列は親行と同位置）。
     *
     * @return list<string> 1 行以上（説明折返し時は複数行）
     */
    public static function formatIndentedExtraRow(
        string $toppingName,
        int $unitPriceMinor,
        int $lineAmountMinor,
    ): array {
        $label = '  - extra: '.trim($toppingName);
        $pu = self::formatAmountCompact($unitPriceMinor, self::W_PU);
        $amt = self::formatAmountCompact($lineAmountMinor, self::W_AMT);

        $descLines = self::wrapTextToWidth($label, self::W_DESC);
        $out = [];
        $first = true;
        foreach ($descLines as $chunk) {
            if ($first) {
                $out[] = self::buildItemRow(
                    str_repeat(' ', self::W_QTY),
                    $chunk,
                    $pu,
                    $amt,
                    padNumeric: false,
                );
                $first = false;
            } else {
                $out[] = self::buildItemContinuationRow($chunk);
            }
        }

        return $out;
    }

    /**
     * 区切り（ハイフン 48）
     */
    public static function separatorLine(): string
    {
        return str_repeat('-', self::LINE_WIDTH);
    }

    /**
     * 単一行を 48 桁に収める（左詰め、はみ出しは mb_strimwidth）
     */
    public static function centerLine(string $text): string
    {
        $w = mb_strwidth($text, 'UTF-8');
        if ($w >= self::LINE_WIDTH) {
            return self::truncateToWidth($text, self::LINE_WIDTH);
        }
        $pad = self::LINE_WIDTH - $w;
        $left = intdiv($pad, 2);

        return str_repeat(' ', $left).$text.str_repeat(' ', $pad - $left);
    }

    /**
     * 左・右にメタ情報（TABLE / DATE 等）
     */
    public static function metaTwoColumnLine(string $left, string $right): string
    {
        $left = self::truncateToWidth($left, self::LINE_WIDTH);
        $right = self::truncateToWidth($right, self::LINE_WIDTH);
        $wl = mb_strwidth($left, 'UTF-8');
        $wr = mb_strwidth($right, 'UTF-8');
        if ($wl + $wr + 1 > self::LINE_WIDTH) {
            $left = self::truncateToWidth($left, max(1, self::LINE_WIDTH - $wr - 1));
            $wl = mb_strwidth($left, 'UTF-8');
        }
        $gap = self::LINE_WIDTH - $wl - $wr;

        return $left.str_repeat(' ', max(1, $gap)).$right;
    }

    /**
     * ラベル: 金額（右寄せ）を 48 桁で
     */
    public static function labelAmountLine(string $label, int $amountMinor, bool $bold = false): string
    {
        unset($bold);
        $amount = ReceiptMoneyColumn::formatMinorCompact($amountMinor);
        $label = self::truncateToWidth($label, self::LINE_WIDTH - 1 - mb_strwidth($amount, 'UTF-8'));

        return self::metaTwoColumnLine($label, $amount);
    }

    /** 合計ブロック用（DT 付き人間可読） */
    public static function totalsDtLine(string $label, int $amountMinor): string
    {
        $right = MenuItemMoney::formatMinorForDisplay($amountMinor);
        $label = self::truncateToWidth($label, max(8, self::LINE_WIDTH - mb_strwidth($right, 'UTF-8') - 1));

        return self::metaTwoColumnLine($label, $right);
    }

    /**
     * TVA 内訳表の 1 行（3 列 16 桁相当）
     */
    public static function vatBreakdownRow(string $rateCol, string $htCol, string $vatCol): string
    {
        $w = 16;
        $a = self::padRight(self::truncateToWidth($rateCol, $w), $w);
        $b = self::padRight(self::truncateToWidth($htCol, $w), $w);
        $c = self::padLeft(self::truncateToWidth($vatCol, $w), $w);

        return $a.$b.$c;
    }

    /**
     * @param  list<string>  $lines
     */
    public static function joinLines(array $lines): string
    {
        return implode("\n", $lines);
    }

    private static function buildItemRow(
        string $qtyCell,
        string $descCell,
        string $puCell,
        string $amtCell,
        bool $padNumeric,
    ): string {
        if ($padNumeric) {
            $qtyCell = self::padLeft(self::truncateToWidth($qtyCell, self::W_QTY), self::W_QTY);
        } else {
            $qtyCell = self::padRight(self::truncateToWidth($qtyCell, self::W_QTY), self::W_QTY);
        }
        $descCell = self::padRight(self::truncateToWidth($descCell, self::W_DESC), self::W_DESC);
        $puCell = self::padLeft(self::truncateToWidth($puCell, self::W_PU), self::W_PU);
        $amtCell = self::padLeft(self::truncateToWidth($amtCell, self::W_AMT), self::W_AMT);

        return $qtyCell
            .str_repeat(' ', self::GAP_QTY_DESC)
            .$descCell
            .str_repeat(' ', self::GAP_DESC_PU)
            .$puCell
            .str_repeat(' ', self::GAP_PU_AMT)
            .$amtCell;
    }

    /** 説明の続き行（他列は空欄相当スペース） */
    private static function buildItemContinuationRow(string $descChunk): string
    {
        $descCell = self::padRight(self::truncateToWidth($descChunk, self::W_DESC), self::W_DESC);
        $tailPad = self::GAP_DESC_PU + self::W_PU + self::GAP_PU_AMT + self::W_AMT;

        return str_repeat(' ', self::W_QTY + self::GAP_QTY_DESC)
            .$descCell
            .str_repeat(' ', $tailPad);
    }

    /**
     * @return list<string>
     */
    private static function wrapTextToWidth(string $text, int $width): array
    {
        $text = trim($text);
        if ($text === '') {
            return [str_repeat(' ', $width)];
        }
        $lines = [];
        $remaining = $text;
        while ($remaining !== '') {
            $chunk = self::truncateToWidth($remaining, $width);
            $lines[] = $chunk;
            $remaining = trim(mb_substr($remaining, mb_strlen($chunk, 'UTF-8'), null, 'UTF-8'));
        }

        return $lines;
    }

    private static function truncateToWidth(string $text, int $maxWidth): string
    {
        if (mb_strwidth($text, 'UTF-8') <= $maxWidth) {
            return $text;
        }

        return rtrim(mb_strimwidth($text, 0, $maxWidth, '', 'UTF-8'));
    }

    private static function padLeft(string $s, int $w): string
    {
        $sw = mb_strwidth($s, 'UTF-8');
        if ($sw >= $w) {
            return self::truncateToWidth($s, $w);
        }

        return str_repeat(' ', $w - $sw).$s;
    }

    private static function padRight(string $s, int $w): string
    {
        $sw = mb_strwidth($s, 'UTF-8');
        if ($sw >= $w) {
            return self::truncateToWidth($s, $w);
        }

        return $s.str_repeat(' ', $w - $sw);
    }

    private static function formatAmountCompact(int $minor, int $maxLen): string
    {
        $s = ReceiptMoneyColumn::formatMinorCompact($minor);

        return self::truncateToWidth($s, $maxLen);
    }
}
