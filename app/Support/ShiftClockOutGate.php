<?php

namespace App\Support;

use App\Models\Attendance;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * レジ締め・チップ確定前の「シフト別 退勤打刻漏れ」検知。
 */
final class ShiftClockOutGate
{
    /**
     * 退勤漏れの Attendance コレクションを返す。
     * staff リレーションをフルでeager loadする（fixed_shifts が必要なため、
     * staff:id,name の絞り込みは不可）。
     *
     * @param  'lunch'|'dinner'  $shift
     * @return Collection<int, Attendance>
     */
    public static function missingClockOutAttendances(string $businessDate, string $shift): Collection
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
            ->with(['staff']) // full load required for fixed_shifts
            ->orderBy('staff_id')
            ->get();
    }

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

    /** Libellé affichage uniquement (ne pas utiliser pour la logique / la base). */
    public static function shiftLabelFr(string $shift): string
    {
        return match ($shift) {
            'lunch' => 'Midi',
            'dinner' => 'Soir',
            default => $shift,
        };
    }

    /**
     * @param  list<string>  $missingStaffNames
     */
    public static function missingClockOutUserMessage(string $shift, array $missingStaffNames): string
    {
        $service = self::shiftLabelFr($shift);
        $names = implode(', ', $missingStaffNames);

        return 'Les employés suivants n\'ont pas pointé leur sortie (service '.$service.'). Veuillez corriger les présences avant de clôturer.'."\n".$names;
    }
}
