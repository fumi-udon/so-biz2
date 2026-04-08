<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\AbsenceScope;
use App\Support\BusinessDate;
use App\Support\StoreHolidaySetting;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class HeadquartersAttendanceArcadeWidget extends Widget
{
    protected static string $view = 'filament.widgets.headquarters-attendance-arcade';

    protected static ?int $sort = -7;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u?->isAdmin() === true || $u?->isCashier() === true || $u?->isPiloteOnly() === true;
    }

    /**
     * @return array{
     *     monthLabel: string,
     *     rows: Collection<int, array{staff: Staff, late: int, absent_equiv: int, warn: bool}>,
     *     maxLate: int,
     * }
     */
    protected function getViewData(): array
    {
        $bd = BusinessDate::current();
        $start = $bd->copy()->startOfMonth()->toDateString();
        $end = $bd->copy()->toDateString();
        $monthLabel = $bd->translatedFormat('Y-m');

        $staffList = Staff::query()
            ->where('is_active', true)
            ->with('jobLevel')
            ->orderBy('name')
            ->get();

        // 当月分 Attendance を一括取得し、スタッフ別 + 日付別にインデックス
        $allAttendances = Attendance::query()
            ->whereIn('staff_id', $staffList->pluck('id'))
            ->whereBetween('date', [$start, $end])
            ->get();

        // [staff_id => [date_string => Attendance]]
        $attendanceMap = [];
        foreach ($allAttendances as $att) {
            $dateStr = Carbon::parse($att->date)->toDateString();
            $attendanceMap[$att->staff_id][$dateStr] = $att;
        }

        // 遅刻カウント: late_minutes > 0 の行数
        $lateCounts = $allAttendances
            ->filter(fn (Attendance $a) => (int) ($a->late_minutes ?? 0) > 0)
            ->groupBy('staff_id')
            ->map(fn (Collection $g) => $g->count());

        $startCarbon = Carbon::parse($start);
        $endCarbon = Carbon::parse($end);

        $holidaySet = StoreHolidaySetting::dateSet();
        $staffIds = $staffList->pluck('id')->all();
        $absenceMapByStaff = AbsenceScope::loadAbsenceMapForStaffInRange($staffIds, $start, $end);

        $rows = $staffList->map(function (Staff $staff) use ($lateCounts, $attendanceMap, $startCarbon, $endCarbon, $holidaySet, $absenceMapByStaff): array {
            $late = (int) ($lateCounts[$staff->id] ?? 0);

            $absentEquiv = 0;
            $cursor = $startCarbon->copy();
            $staffAttByDate = $attendanceMap[$staff->id] ?? [];
            while ($cursor->lte($endCarbon)) {
                $d = $cursor->toDateString();
                $row = $staffAttByDate[$d] ?? null;
                $hasAbs = isset($absenceMapByStaff[$staff->id][$d]);
                if (AbsenceScope::resolveDay($d, $row, $holidaySet, $hasAbs) === AbsenceScope::STATUS_ABSENT) {
                    $absentEquiv++;
                }
                $cursor->addDay();
            }

            $warn = $late >= 3 || $absentEquiv >= 2;

            return [
                'staff' => $staff,
                'late' => $late,
                'absent_equiv' => $absentEquiv,
                'warn' => $warn,
            ];
        })->values();

        $maxLate = (int) $rows->max(fn (array $r): int => $r['late']);

        return [
            'monthLabel' => $monthLabel,
            'rows' => $rows,
            'maxLate' => max(1, $maxLate),
        ];
    }
}
