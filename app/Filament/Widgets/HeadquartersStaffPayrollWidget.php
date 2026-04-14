<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\BusinessDate;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class HeadquartersStaffPayrollWidget extends Widget
{
    protected static string $view = 'filament.widgets.headquarters-staff-payroll';

    protected static ?int $sort = -6;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();
        if ($u === null) {
            return false;
        }

        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');

        return $u->hasRole('Owner') || $u->hasRole($superAdminName);
    }

    /**
     * 月間労働時間（分）× 時給 ÷ 60 で Éq. paye を計算する。
     * 浮動小数点誤差を避けるため「分 × 時給 ÷ 60」の順で計算し、最後に 3 桁で丸める。
     */
    private function calcMonthlyEquivPaye(Staff $staff, int $year, int $month): ?float
    {
        $hourlyWage = $staff->hourly_wage;
        if ($hourlyWage === null || (float) $hourlyWage <= 0.0) {
            return null;
        }

        $sumMinutes = (int) Attendance::query()
            ->where('staff_id', $staff->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get()
            ->sum(fn (Attendance $a): int => (int) ($a->workMinutes() ?? 0));

        if ($sumMinutes <= 0) {
            return null;
        }

        return round($sumMinutes * (float) $hourlyWage / 60, 3);
    }

    /**
     * @return array{
     *     staff: Collection<int, Staff>,
     *     totalWage: float,
     *     totalHourlyLabel: string,
     *     payrollYear: int,
     *     payrollMonth: int,
     *     equivPaye: array<int, float|null>,
     * }
     */
    protected function getViewData(): array
    {
        $staff = Staff::query()
            ->where('is_active', true)
            ->with('jobLevel')
            ->orderBy('name')
            ->get();

        $totalWage = (float) $staff->sum(fn (Staff $s): float => (float) ($s->wage ?? 0));
        $withHourly = $staff->filter(fn (Staff $s): bool => $s->hourly_wage !== null && (float) $s->hourly_wage > 0.0)->count();

        $businessDate = BusinessDate::current();
        $year = (int) $businessDate->year;
        $month = (int) $businessDate->month;

        $equivPaye = [];
        foreach ($staff as $s) {
            $equivPaye[$s->id] = $this->calcMonthlyEquivPaye($s, $year, $month);
        }

        return [
            'staff' => $staff,
            'totalWage' => $totalWage,
            'totalHourlyLabel' => $withHourly > 0 ? $withHourly.' membre(s) à l\'heure' : '—',
            'payrollYear' => $year,
            'payrollMonth' => $month,
            'equivPaye' => $equivPaye,
        ];
    }
}
