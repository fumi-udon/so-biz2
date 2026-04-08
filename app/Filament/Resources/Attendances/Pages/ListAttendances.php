<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Widgets\TodayAttendanceRosterWidget;
use Filament\Resources\Pages\ListRecords;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class ListAttendances extends ListRecords
{
    protected static string $resource = AttendanceResource::class;

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TodayAttendanceRosterWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
