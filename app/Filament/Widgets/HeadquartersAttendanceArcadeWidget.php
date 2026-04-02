<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\BusinessDate;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class HeadquartersAttendanceArcadeWidget extends Widget
{
    protected static string $view = 'filament.widgets.headquarters-attendance-arcade';

    protected static ?int $sort = -7;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u?->isAdmin() === true || $u?->isCashier() === true;
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
        $end = $bd->copy()->endOfMonth()->toDateString();
        $monthLabel = $bd->translatedFormat('Y-m');

        $lateCounts = Attendance::query()
            ->selectRaw('staff_id, COUNT(*) as c')
            ->whereBetween('date', [$start, $end])
            ->where('late_minutes', '>', 0)
            ->groupBy('staff_id')
            ->pluck('c', 'staff_id');

        $noPunchCounts = Attendance::query()
            ->selectRaw('staff_id, COUNT(*) as c')
            ->whereBetween('date', [$start, $end])
            ->whereNull('lunch_in_at')
            ->whereNull('dinner_in_at')
            ->where(function ($q): void {
                $q->whereNull('late_minutes')->orWhere('late_minutes', '=', 0);
            })
            ->groupBy('staff_id')
            ->pluck('c', 'staff_id');

        $staffList = Staff::query()
            ->where('is_active', true)
            ->with('jobLevel')
            ->orderBy('name')
            ->get();

        $rows = $staffList->map(function (Staff $staff) use ($lateCounts, $noPunchCounts): array {
            $late = (int) ($lateCounts[$staff->id] ?? 0);
            $absentEquiv = (int) ($noPunchCounts[$staff->id] ?? 0);
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
