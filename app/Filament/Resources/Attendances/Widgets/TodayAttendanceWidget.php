<?php

namespace App\Filament\Resources\Attendances\Widgets;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\BusinessDate;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class TodayAttendanceWidget extends Widget
{
    protected string $view = 'filament.resources.attendances.widgets.today-attendance';

    protected static ?int $sort = -9;

    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array{
     *     businessDate: string,
     *     staffRows: Collection<int, array{staff: Staff, attendance: Attendance|null, status: string}>
     * }
     */
    protected function getViewData(): array
    {
        $businessDate = BusinessDate::current();
        $dateString = $businessDate->toDateString();

        $staff = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $attendances = Attendance::query()
            ->whereDate('date', $dateString)
            ->get()
            ->keyBy('staff_id');

        $rows = $staff->map(function (Staff $s) use ($attendances): array {
            /** @var Attendance|null $att */
            $att = $attendances->get($s->id);
            $status = 'absent';
            if ($att !== null) {
                $workingLunch = $att->lunch_in_at && ! $att->lunch_out_at;
                $workingDinner = $att->dinner_in_at && ! $att->dinner_out_at;
                $status = ($workingLunch || $workingDinner) ? 'working' : 'off';
            }

            return [
                'staff' => $s,
                'attendance' => $att,
                'status' => $status,
            ];
        });

        return [
            'businessDate' => $dateString,
            'staffRows' => $rows,
        ];
    }
}
