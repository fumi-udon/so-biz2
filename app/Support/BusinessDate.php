<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * タイムカード・クローズチェックと同一の「営業日」（6時未満は前日扱い）。
 */
final class BusinessDate
{
    public static function current(): Carbon
    {
        $now = now();

        if ($now->hour < 6) {
            return $now->copy()->subDay()->startOfDay();
        }

        return $now->copy()->startOfDay();
    }

    public static function toDateString(): string
    {
        return self::current()->toDateString();
    }
}
