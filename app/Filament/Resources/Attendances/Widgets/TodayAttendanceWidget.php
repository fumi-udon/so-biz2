<?php

namespace App\Filament\Resources\Attendances\Widgets;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\AbsenceScope;
use App\Support\BusinessDate;
use App\Support\StoreHolidaySetting;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

class TodayAttendanceWidget extends Widget
{
    protected static string $view = 'filament.resources.attendances.widgets.today-attendance';

    protected static ?int $sort = -9;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array{
     *     businessDate: string,
     *     staffRows: Collection<int, array{staff: Staff, attendance: Attendance|null, status: 'working'|'off'|'absent'|'pending'|'holiday'}>
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

        $holidaySet = StoreHolidaySetting::dateSet();
        $staffIds = $staff->pluck('id')->all();
        $absenceMap = AbsenceScope::loadAbsenceMapForStaffInRange($staffIds, $dateString, $dateString);

        $rows = $staff->map(function (Staff $s) use ($attendances, $holidaySet, $absenceMap, $dateString): array {
            /** @var Attendance|null $att */
            $att = $attendances->get($s->id);
            $status = 'pending';
            if ($att !== null) {
                $workingLunch = $att->lunch_in_at && ! $att->lunch_out_at;
                $workingDinner = $att->dinner_in_at && ! $att->dinner_out_at;
                $status = ($workingLunch || $workingDinner) ? 'working' : 'off';
            } else {
                $hasAbs = isset($absenceMap[$s->id][$dateString]);
                $resolved = AbsenceScope::resolveDay($dateString, null, $holidaySet, $hasAbs);
                $status = match ($resolved) {
                    AbsenceScope::STATUS_NOT_ABSENT => 'holiday',
                    AbsenceScope::STATUS_ABSENT => 'absent',
                    default => 'pending',
                };
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
