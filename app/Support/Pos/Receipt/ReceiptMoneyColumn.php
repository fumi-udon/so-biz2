<?php

namespace App\Support\Pos\Receipt;

/**
 * レシート列幅向けの短い金額表記（DT 接尾辞なし、小数 3 桁まで）。
 */
final class ReceiptMoneyColumn
{
    public static function formatMinorCompact(int $minor): string
    {
        $minor = max(0, $minor);
        $dt = $minor / 1000.0;

        return number_format($dt, 3, '.', '');
    }
}
