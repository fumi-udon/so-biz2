<?php

namespace App\Support;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;

/**
 * レジ締め・チップ確定前の「シフト別 退勤打刻漏れ」検知。
 */
final class ShiftClockOutGate
{
    /**
     * @return list<string> 重複なしのスタッフ名（空ならブロック不要）
     */
    public static function missingClockOutStaffNames(string $businessDate, string $shift): array
    {
        $query = Attendance::query()
            ->whereDate('date', $businessDate)
            ->whereHas('staff', function (Builder $q): void {
                $q->where('is_active', true);
            });

        match ($shift) {
            'lunch' => $query->whereNotNull('lunch_in_at')->whereNull('lunch_out_at'),
            'dinner' => $query->whereNotNull('dinner_in_at')->whereNull('dinner_out_at'),
            default => $query->whereRaw('0 = 1'),
        };

        return $query
            ->with('staff:id,name')
            ->orderBy('staff_id')
            ->get()
            ->pluck('staff.name')
            ->filter(fn ($name): bool => is_string($name) && $name !== '')
            ->unique()
            ->values()
            ->all();
    }
}
