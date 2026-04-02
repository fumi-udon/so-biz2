<?php

namespace App\Filament\Widgets;

use App\Models\Staff;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class HeadquartersStaffPayrollWidget extends Widget
{
    protected static string $view = 'filament.widgets.headquarters-staff-payroll';

    protected static ?int $sort = -6;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $u = auth()->user();

        return $u?->isAdmin() === true || $u?->isCashier() === true;
    }

    /**
     * @return array{
     *     staff: Collection<int, Staff>,
     *     totalWage: float,
     *     totalHourlyLabel: string,
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
        $withHourly = $staff->filter(fn (Staff $s): bool => $s->hourly_wage !== null && (int) $s->hourly_wage > 0)->count();

        return [
            'staff' => $staff,
            'totalWage' => $totalWage,
            'totalHourlyLabel' => $withHourly > 0 ? $withHourly.' 名が時給制' : '—',
        ];
    }
}
