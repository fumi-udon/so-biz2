<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Widgets\TodayAttendanceWidget;
use App\Models\Attendance;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->header(fn (): View => view('filament.resources.attendances.components.summary-banner', [
                'stats' => $this->buildAttendanceSummaryStats(),
            ]));
    }

    /**
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TodayAttendanceWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    /**
     * @return array{
     *     total_minutes: int,
     *     total_hours_decimal: float,
     *     late_count: int,
     *     late_total_minutes: int,
     *     day_count: int
     * }
     */
    protected function buildAttendanceSummaryStats(): array
    {
        $defaults = [
            'total_minutes' => 0,
            'total_hours_decimal' => 0.0,
            'late_count' => 0,
            'late_total_minutes' => 0,
            'day_count' => 0,
        ];

        $query = $this->getFilteredTableQuery();

        if (! $query instanceof Builder) {
            return $defaults;
        }

        /** @var Collection<int, Attendance> $rows */
        $rows = $query->clone()->get();

        $totalMinutes = 0;
        $lateCount = 0;
        $lateTotalMinutes = 0;

        foreach ($rows as $row) {
            $totalMinutes += $row->workMinutes() ?? 0;

            if (($row->late_minutes ?? 0) > 0) {
                $lateCount++;
                $lateTotalMinutes += (int) $row->late_minutes;
            }
        }

        return [
            'total_minutes' => $totalMinutes,
            'total_hours_decimal' => round($totalMinutes / 60, 2),
            'late_count' => $lateCount,
            'late_total_minutes' => $lateTotalMinutes,
            'day_count' => $rows->count(),
        ];
    }
}
