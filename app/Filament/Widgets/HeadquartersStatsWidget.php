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

    protected ?string $heading = '本日の本部サマリー';

    protected ?string $description = '営業日基準（BusinessDate）';

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

        $laborCost = 0.0;
        foreach ($attendances as $attendance) {
            $minutes = $attendance->workMinutes();
            $wage = $attendance->staff?->hourly_wage;
            if ($minutes !== null && $minutes > 0 && $wage !== null && (int) $wage > 0) {
                $laborCost += ($minutes / 60) * (int) $wage;
            }
        }

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
                '本日のリアルタイム人件費',
                number_format($laborCost, 0, '.', ' ').' TND',
            )
                ->description('確定済み勤務区間 × 時給の合計')
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make(
                '本日の出勤人数',
                (string) $attendanceCount,
            )
                ->description('Attendance レコード数')
                ->icon('heroicon-o-users')
                ->color('info'),
            Stat::make(
                '本日のタスク完了率',
                $taskRateLabel,
            )
                ->description($totalTasks > 0 ? "完了 {$completedCount} / 全 {$totalTasks} 件" : '出勤者に割当のタスクなし')
                ->icon('heroicon-o-check-circle')
                ->color('warning'),
        ];
    }
}
