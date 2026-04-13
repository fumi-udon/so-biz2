<?php

namespace App\Support;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;

/**
 * チップ配分対象の抽出条件（フラグ優先 + 非剥奪）を一元化する。
 *
 * 打刻時間（lunch_in_at / dinner_in_at）は条件から除外。
 * 管理者が手動付与（is_*_tip_applied = true）したレコードも対象とする。
 *
 * ランチ: is_lunch_tip_applied = true AND (is_lunch_tip_denied = false OR NULL)
 * ディナー: is_dinner_tip_applied = true AND (is_dinner_tip_denied = false OR NULL)
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
                ->where('is_lunch_tip_applied', true)
                ->where(fn ($q) => $q->where('is_lunch_tip_denied', false)->orWhereNull('is_lunch_tip_denied')),
            'dinner' => $query
                ->where('is_dinner_tip_applied', true)
                ->where(fn ($q) => $q->where('is_dinner_tip_denied', false)->orWhereNull('is_dinner_tip_denied')),
            default => $query,
        };
    }

    public static function lunchEligible(Attendance $row): bool
    {
        return (bool) ($row->is_lunch_tip_applied ?? false)
            && ! (bool) ($row->is_lunch_tip_denied ?? false);
    }

    public static function dinnerEligible(Attendance $row): bool
    {
        return (bool) ($row->is_dinner_tip_applied ?? false)
            && ! (bool) ($row->is_dinner_tip_denied ?? false);
    }
}
