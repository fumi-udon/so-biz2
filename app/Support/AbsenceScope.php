<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\StaffAbsence;

/**
 * 欠勤・未確定の判定を単一箇所に集約する（優先順位固定）。
 *
 * 1. 店舗休業日 → 欠勤ではない
 * 2. 出勤実績（打刻または tip-only 保護）がある → 欠勤ではない
 * 3. 確定欠勤 StaffAbsence がある → 欠勤
 * 4. 上記以外 → 未確定（候補）
 */
final class AbsenceScope
{
    public const STATUS_NOT_ABSENT = 'not_absent';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_PENDING = 'pending';

    /**
     * その日の Attendance 行に「出勤実績」があるか（打刻または tip-only）。
     */
    public static function hasAttendanceWorkDay(?Attendance $row): bool
    {
        if ($row === null) {
            return false;
        }

        if ($row->lunch_in_at !== null || $row->dinner_in_at !== null) {
            return true;
        }

        $lunchTipOnly = (bool) ($row->is_lunch_tip_applied ?? false)
            && ! (bool) ($row->is_lunch_tip_denied ?? false);
        $dinnerTipOnly = (bool) ($row->is_dinner_tip_applied ?? false)
            && ! (bool) ($row->is_dinner_tip_denied ?? false);

        return $lunchTipOnly || $dinnerTipOnly;
    }

    /**
     * @param  array<string, true>  $holidaySet  Y-m-d
     * @param  array<int, array<string, true>>  $absenceMapByStaff  [staff_id][Y-m-d] => true
     */
    public static function resolveDay(
        string $dateYmd,
        ?Attendance $row,
        array $holidaySet,
        bool $hasStaffAbsenceOnDay,
    ): string {
        if (isset($holidaySet[$dateYmd])) {
            return self::STATUS_NOT_ABSENT;
        }

        if (self::hasAttendanceWorkDay($row)) {
            return self::STATUS_NOT_ABSENT;
        }

        if ($hasStaffAbsenceOnDay) {
            return self::STATUS_ABSENT;
        }

        return self::STATUS_PENDING;
    }

    /**
     * @param  list<int>  $staffIds
     * @return array<int, array<string, true>> [staff_id][Y-m-d] => true
     */
    public static function loadAbsenceMapForStaffInRange(array $staffIds, string $startYmd, string $endYmd): array
    {
        if ($staffIds === []) {
            return [];
        }

        $rows = StaffAbsence::query()
            ->whereIn('staff_id', $staffIds)
            ->whereBetween('date', [$startYmd, $endYmd])
            ->get(['staff_id', 'date']);

        $map = [];
        foreach ($rows as $r) {
            $sid = (int) $r->staff_id;
            $d = $r->date->toDateString();
            $map[$sid] ??= [];
            $map[$sid][$d] = true;
        }

        return $map;
    }
}
