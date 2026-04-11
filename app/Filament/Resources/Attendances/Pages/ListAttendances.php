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
     * @var view-string
     */
    protected static string $view = 'filament.resources.attendances.pages.list-attendances';

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    protected function getHeaderWidgets(): array
    {
        // return [
        //     TodayAttendanceRosterWidget::class,
        // ];
        return [];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
