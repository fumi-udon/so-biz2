<?php

namespace App\Filament\Resources\Attendances\Widgets;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\BusinessDate;
use App\Support\FixedShiftSchedule;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TodayAttendanceRosterWidget extends Widget
{
    protected static string $view = 'filament.resources.attendances.widgets.today-attendance-roster';

    protected static ?int $sort = -10;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    private const GRACE_MINUTES_AFTER_PLANNED_START = 10;

    /**
     * @return array{
     *     businessDate: string,
     *     rows: Collection<int, array{
     *         staff: Staff,
     *         attendance: Attendance|null,
     *         role_label: string,
     *         role_color: string,
     *         role_category: string,
     *         lunch_scheduled_start: string|null,
     *         lunch_in_time: string|null,
     *         lunch_status: string,
     *         dinner_scheduled_start: string|null,
     *         dinner_in_time: string|null,
     *         dinner_status: string,
     *         lunchPlan: string,
     *         dinnerPlan: string,
     *         status: string,
     *         statusLabel: string,
     *         statusColor: string,
     *     }>
     * }
     */
    protected function getViewData(): array
    {
        $businessDate = BusinessDate::current();
        $dateString = $businessDate->toDateString();
        $dayKey = strtolower($businessDate->englishDayOfWeek);
        $businessStart = $businessDate->copy()->startOfDay();

        $attendances = Attendance::query()
            ->whereDate('date', $dateString)
            ->get()
            ->keyBy('staff_id');

        $rows = Staff::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->filter(fn (Staff $staff) => $attendances->has($staff->id) || FixedShiftSchedule::hasShiftOnDay($staff, $dayKey))
            ->map(function (Staff $staff) use ($attendances, $dayKey, $businessStart): array {
                $att = $attendances->get($staff->id);

                $lunchScheduledStart = FixedShiftSchedule::start($staff, $dayKey, 'lunch');
                $dinnerScheduledStart = FixedShiftSchedule::start($staff, $dayKey, 'dinner');

                $lunchStatusData = self::resolveMealLiveStatus(
                    $businessStart,
                    $lunchScheduledStart,
                    $att?->lunch_in_at,
                );
                $dinnerStatusData = self::resolveMealLiveStatus(
                    $businessStart,
                    $dinnerScheduledStart,
                    $att?->dinner_in_at,
                );

                $roleRaw = (string) ($staff->role ?? '');
                $roleCategory = self::roleCategory($roleRaw);
                $roleLabel = match ($roleCategory) {
                    'kitchen' => 'Kitchen',
                    'hall' => 'Hall',
                    default => 'Other',
                };
                $roleColor = match ($roleCategory) {
                    'kitchen' => 'red',
                    'hall' => 'green',
                    default => 'gray',
                };

                $lunchPlan = self::formatShiftPlan($staff, $dayKey, 'lunch');
                $dinnerPlan = self::formatShiftPlan($staff, $dayKey, 'dinner');
                [$status, $statusLabel, $statusColor] = self::resolveStatus($staff, $att, $dayKey);

                return [
                    'staff' => $staff,
                    'attendance' => $att,
                    'role_label' => $roleLabel,
                    'role_color' => $roleColor,
                    'role_category' => $roleCategory,
                    'lunch_scheduled_start' => $lunchScheduledStart,
                    'lunch_in_time' => $lunchStatusData['in_time'],
                    'lunch_status' => $lunchStatusData['status'],
                    'dinner_scheduled_start' => $dinnerScheduledStart,
                    'dinner_in_time' => $dinnerStatusData['in_time'],
                    'dinner_status' => $dinnerStatusData['status'],
                    'lunchPlan' => $lunchPlan,
                    'dinnerPlan' => $dinnerPlan,
                    'status' => $status,
                    'statusLabel' => $statusLabel,
                    'statusColor' => $statusColor,
                    'role_raw' => $roleRaw,
                ];
            });

        $rows = $rows->values()->sort(function (array $a, array $b): int {
            $order = ['kitchen' => 0, 'hall' => 1, 'other' => 2];
            $ca = $order[$a['role_category']] ?? 2;
            $cb = $order[$b['role_category']] ?? 2;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            $r = strcasecmp((string) ($a['role_raw'] ?? ''), (string) ($b['role_raw'] ?? ''));
            if ($r !== 0) {
                return $r;
            }

            $na = (string) ($a['staff']->name ?? '');
            $nb = (string) ($b['staff']->name ?? '');

            return strcasecmp($na, $nb);
        })->values();

        return [
            'businessDate' => $dateString,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{status: string, in_time: string|null}
     */
    private static function resolveMealLiveStatus(Carbon $businessStart, ?string $scheduledStart, ?Carbon $inAt): array
    {
        $hasScheduledShift = filled($scheduledStart);

        if ($inAt && (! $hasScheduledShift)) {
            return ['status' => 'extra', 'in_time' => $inAt->format('H:i')];
        }

        if ($inAt && $hasScheduledShift) {
            return ['status' => 'clocked', 'in_time' => $inAt->format('H:i')];
        }

        if (! $hasScheduledShift) {
            return ['status' => 'none', 'in_time' => null];
        }

        if (! $scheduledStart) {
            return ['status' => 'none', 'in_time' => null];
        }

        try {
            [$h, $m] = array_map('intval', explode(':', trim($scheduledStart), 2));
            $deadline = $businessStart->copy()->setTime($h, $m, 0)->addMinutes(self::GRACE_MINUTES_AFTER_PLANNED_START);
        } catch (\Throwable) {
            return ['status' => 'future', 'in_time' => null];
        }

        if (now()->greaterThan($deadline)) {
            return ['status' => 'late', 'in_time' => null];
        }

        return ['status' => 'future', 'in_time' => null];
    }

    private static function roleCategory(string $role): string
    {
        $r = strtolower(trim($role));
        if ($r === '') {
            return 'other';
        }

        $kitchenNeedles = ['kitchen', 'chef', 'cook', 'cuisine', 'commis', 'patissier', 'pâtissier', 'boulanger'];
        $hallNeedles = ['hall', 'waiter', 'waitress', 'service', 'server', 'salle', 'floor', 'serveur', 'serveuse'];

        foreach ($kitchenNeedles as $needle) {
            if (str_contains($r, $needle)) {
                return 'kitchen';
            }
        }

        foreach ($hallNeedles as $needle) {
            if (str_contains($r, $needle)) {
                return 'hall';
            }
        }

        return 'other';
    }

    private static function formatShiftPlan(Staff $staff, string $dayKey, string $mealKey): string
    {
        $fixed = $staff->fixed_shifts;
        if (! is_array($fixed)) {
            return '—';
        }

        $dayShift = $fixed[$dayKey] ?? null;
        if (! is_array($dayShift)) {
            return '—';
        }

        $slot = $dayShift[$mealKey] ?? null;
        if (! is_array($slot)) {
            return '—';
        }

        $start = isset($slot[0]) && is_string($slot[0]) ? trim($slot[0]) : '';
        $end = isset($slot[1]) && is_string($slot[1]) ? trim($slot[1]) : '';
        if ($start === '') {
            return '—';
        }

        return $end !== '' ? $start.'–'.$end : $start;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private static function resolveStatus(Staff $staff, ?Attendance $att, string $dayKey): array
    {
        $scheduled = FixedShiftSchedule::hasShiftOnDay($staff, $dayKey);

        if ($att === null) {
            return ['no_show', '⚠ 未打刻（予定あり）', 'warning'];
        }

        if ($att->hasMissingClockOut()) {
            return ['working', '勤務中', 'success'];
        }

        $hasAnyPunch = $att->lunch_in_at !== null || $att->dinner_in_at !== null;
        if (! $hasAnyPunch && $scheduled) {
            return ['idle', '未打刻', 'warning'];
        }

        if (! $hasAnyPunch) {
            return ['idle', '打刻なし', 'gray'];
        }

        return ['finished', '退勤済', 'gray'];
    }
}
