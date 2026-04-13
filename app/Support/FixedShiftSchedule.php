<?php

namespace App\Support;

use App\Models\Staff;

/**
 * Staff::fixed_shifts の配列からシフト定義を取り出すユーティリティ。
 * MyPageController / TodayAttendanceRosterWidget / TimecardPunchService で
 * 同じロジックが重複していたため、ここに一本化する。
 */
class FixedShiftSchedule
{
    /**
     * 指定した曜日・食事区分のシフト開始時刻（"HH:MM" 文字列）を返す。
     * シフト未定義・フォーマット不正なら null。
     *
     * @param  'lunch'|'dinner'|string  $mealKey
     */
    public static function start(Staff $staff, string $dayKey, string $mealKey): ?string
    {
        $slot = data_get($staff->fixed_shifts, "{$dayKey}.{$mealKey}");

        if (! is_array($slot) || ! isset($slot[0]) || ! is_string($slot[0])) {
            return null;
        }

        $s = trim($slot[0]);

        return $s !== '' ? $s : null;
    }

    /**
     * 指定した曜日・食事区分のシフト終了時刻（"HH:MM" 文字列）を返す。
     * シフト未定義・$slot[1] 非存在・フォーマット不正なら null。
     *
     * @param  'lunch'|'dinner'|string  $mealKey
     */
    public static function end(Staff $staff, string $dayKey, string $mealKey): ?string
    {
        $slot = data_get($staff->fixed_shifts, "{$dayKey}.{$mealKey}");

        if (! is_array($slot) || ! isset($slot[1]) || ! is_string($slot[1])) {
            return null;
        }

        $s = trim($slot[1]);

        return $s !== '' ? $s : null;
    }

    /**
     * 指定した曜日にランチまたはディナーいずれかのシフトが存在するか。
     */
    public static function hasShiftOnDay(Staff $staff, string $dayKey): bool
    {
        return filled(self::start($staff, $dayKey, 'lunch'))
            || filled(self::start($staff, $dayKey, 'dinner'));
    }
}
