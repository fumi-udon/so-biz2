<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Widgets\TodayAttendanceRosterWidget;
use Filament\Resources\Pages\ListRecords;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    /**
     * @return array<class-string<\Filament\Widgets\Widget> | \Filament\Widgets\WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TodayAttendanceRosterWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
}
