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
        $now = now(config('app.business_timezone'));

        if ($now->hour < 6) {
            return $now->copy()->subDay()->startOfDay();
        }

        return $now->copy()->startOfDay();
    }

    public static function toDateString(): string
    {
        return self::current()->toDateString();
    }

    /**
     * 営業日ベースで時刻文字列を解釈する。0〜5時は「翌日の深夜」として addDay（飲食店ルール）。
     */
    public static function parseTimeForBusinessDate(?string $timeString, Carbon $businessDate): ?Carbon
    {
        if ($timeString === null || trim($timeString) === '') {
            return null;
        }

        if (! preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($timeString), $m)) {
            return null;
        }

        $hour = (int) $m[1];
        $minute = (int) $m[2];
        $second = isset($m[3]) ? (int) $m[3] : 0;

        $date = $businessDate->copy()->startOfDay()->setTime($hour, $minute, $second);

        // 飲食店ルール：0〜5時は「翌日（深夜）」扱い
        if ($hour >= 0 && $hour <= 5) {
            $date->addDay();
        }

        return $date;
    }
}
