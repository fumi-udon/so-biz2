<?php

namespace App\Support;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;

/**
 * チップ配分対象の抽出条件（打刻 + 申請 + 非剥奪）を一元化する。
 *
 * ランチ: lunch_in_at IS NOT NULL AND is_lunch_tip_applied = true AND is_lunch_tip_denied = false
 * ディナー: dinner_in_at IS NOT NULL AND is_dinner_tip_applied = true AND is_dinner_tip_denied = false
 */
class TipAttendanceScope
{
    /**
     * @param  'lunch'|'dinner'  $shift
     */
    public static function applyGoldenFormula(Builder $query, string $shift): Builder
    {
        return match ($shift) {
            'lunch' => $query
                ->whereNotNull('lunch_in_at')
                ->where('is_lunch_tip_applied', true)
                ->where('is_lunch_tip_denied', false),
            'dinner' => $query
                ->whereNotNull('dinner_in_at')
                ->where('is_dinner_tip_applied', true)
                ->where('is_dinner_tip_denied', false),
            default => $query,
        };
    }

    public static function lunchEligible(Attendance $row): bool
    {
        return $row->lunch_in_at !== null
            && (bool) ($row->is_lunch_tip_applied ?? false)
            && ! (bool) ($row->is_lunch_tip_denied ?? false);
    }

    public static function dinnerEligible(Attendance $row): bool
    {
        return $row->dinner_in_at !== null
            && (bool) ($row->is_dinner_tip_applied ?? false)
            && ! (bool) ($row->is_dinner_tip_denied ?? false);
    }
}
