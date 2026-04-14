<?php

namespace App\Filament\Widgets;

use App\Models\Attendance;
use App\Models\RoutineTask;
use App\Models\RoutineTaskLog;
use App\Support\BusinessDate;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HeadquartersStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected ?string $heading = 'Résumé siège (jour)';

    protected ?string $description = 'Jour d’activité (BusinessDate)';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $dateString = BusinessDate::toDateString();

        $attendances = Attendance::query()
            ->whereDate('date', $dateString)
            ->with(['staff' => fn ($q) => $q->withTrashed()])
            ->get();

        $laborCostMilliemes = 0;
        foreach ($attendances as $attendance) {
            $minutes = $attendance->workMinutes();
            $wage = $attendance->staff?->hourly_wage;
            if ($minutes !== null && $minutes > 0 && $wage !== null && (float) $wage > 0.0) {
                // 分 × 時給 ÷ 60 の順で計算し整数ミリーム単位に変換（累積浮動小数点誤差を最小化）
                $laborCostMilliemes += (int) round($minutes * (float) $wage / 60 * 1000);
            }
        }
        $laborCost = round($laborCostMilliemes / 1000, 3);

        $attendanceCount = $attendances->count();

        $attendanceStaffIds = $attendances->pluck('staff_id')->unique()->values()->all();

        $totalTasks = 0;
        $completedCount = 0;

        if ($attendanceStaffIds !== []) {
            $taskIds = RoutineTask::query()
                ->whereIn('assigned_staff_id', $attendanceStaffIds)
                ->where('is_active', true)
                ->pluck('id');

            $totalTasks = $taskIds->count();

            if ($totalTasks > 0) {
                $completedCount = RoutineTaskLog::query()
                    ->whereDate('date', $dateString)
                    ->whereIn('routine_task_id', $taskIds->all())
                    ->count();
            }
        }

        $taskRateLabel = $totalTasks > 0
            ? number_format((float) round(100 * $completedCount / $totalTasks, 1), 1, '.', '').'%'
            : '—';

        return [
            Stat::make(
                'Masse salariale (temps réel)',
                number_format($laborCost, 3, '.', ' ').' DT',
            )
                ->description('Tranches validées × taux horaire')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make(
                'Présences (lignes)',
                (string) $attendanceCount,
            )
                ->description('Nombre d’enregistrements présence')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make(
                'Tâches complétées',
                $taskRateLabel,
            )
                ->description($totalTasks > 0 ? "Fait {$completedCount} / {$totalTasks}" : 'Aucune tâche assignée aux présents')
                ->icon('heroicon-o-check-circle')
                ->color('warning'),
        ];
    }
}
