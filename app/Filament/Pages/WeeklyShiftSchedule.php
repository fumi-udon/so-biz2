<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use App\Models\Attendance;
use App\Models\Staff;
use App\Services\AttendanceStatusResolver;
use App\Support\BusinessDate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class WeeklyShiftSchedule extends AdminOnlyPage
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationGroup = '店舗・勤怠管理';

    protected static ?string $title = '週間シフト表 (Horaires Hebdomadaires)';

    protected static ?string $navigationLabel = '週間シフト表';

    protected static string $view = 'filament.pages.weekly-shift-schedule';

    /** @var array<string, string> */
    private const DAY_LABELS = [
        'monday' => 'Lun (月)',
        'tuesday' => 'Mar (火)',
        'wednesday' => 'Mer (水)',
        'thursday' => 'Jeu (木)',
        'friday' => 'Ven (金)',
        'saturday' => 'Sam (土)',
        'sunday' => 'Dim (日)',
    ];

    /** @var list<string> */
    private const DAY_KEYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    protected function getViewData(): array
    {
        $staffs = Staff::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $today = BusinessDate::current();
        $todayDayKey = strtolower($today->englishDayOfWeek);

        $attendancesToday = Attendance::query()
            ->whereDate('date', $today->toDateString())
            ->get()
            ->keyBy('staff_id');

        $shiftGrid = [];
        foreach (self::DAY_KEYS as $dayKey) {
            $shiftGrid[$dayKey] = [
                'lunch' => $this->buildShiftBlock($staffs, $dayKey, 'lunch', $todayDayKey, $attendancesToday),
                'dinner' => $this->buildShiftBlock($staffs, $dayKey, 'dinner', $todayDayKey, $attendancesToday),
            ];
        }

        $liveByStaff = $this->buildLiveStatusByStaffForToday($staffs, $todayDayKey, $attendancesToday);

        return [
            'staffs' => $staffs,
            'shiftGrid' => $shiftGrid,
            'dayLabels' => self::DAY_LABELS,
            'todayDayKey' => $todayDayKey,
            'attendancesToday' => $attendancesToday,
            'liveByStaff' => $liveByStaff,
        ];
    }

    /**
     * 本日（営業日）の列用: スタッフごとに lunch / dinner のライブステータス。
     *
     * @return array<int, array{lunch: string, dinner: string}>
     */
    private function buildLiveStatusByStaffForToday(Collection $staffs, string $todayDayKey, Collection $attendancesByStaffId): array
    {
        $out = [];
        foreach ($staffs as $staff) {
            $att = $attendancesByStaffId->get($staff->id);
            $dayShift = $staff->fixed_shifts[$todayDayKey] ?? null;
            $lunch = is_array($dayShift) ? ($dayShift['lunch'] ?? null) : null;
            $dinner = is_array($dayShift) ? ($dayShift['dinner'] ?? null) : null;
            $hasLunch = is_array($lunch) && isset($lunch[0]) && is_string($lunch[0]) && trim($lunch[0]) !== '';
            $hasDinner = is_array($dinner) && isset($dinner[0]) && is_string($dinner[0]) && trim($dinner[0]) !== '';

            $out[$staff->id] = [
                'lunch' => $this->resolveMealLiveStatus(
                    $att,
                    $hasLunch,
                    $hasLunch ? ($lunch[0] ?? null) : null,
                    'lunch',
                ),
                'dinner' => $this->resolveMealLiveStatus(
                    $att,
                    $hasDinner,
                    $hasDinner ? ($dinner[0] ?? null) : null,
                    'dinner',
                ),
            ];
        }

        return $out;
    }

    /**
     * @return array{assignments: list<array<string, mixed>>, counts: array{kitchen: int, hall: int, other: int}, live_extras: list<array<string, mixed>>}
     */
    private function buildShiftBlock(
        Collection $staffs,
        string $dayKey,
        string $meal,
        string $todayDayKey,
        Collection $attendancesByStaffId,
    ): array {
        $assignments = [];
        $isToday = $dayKey === $todayDayKey;

        foreach ($staffs as $staff) {
            $shift = data_get($staff->fixed_shifts, "{$dayKey}.{$meal}");
            if (! is_array($shift) || ! isset($shift[0]) || ! is_string($shift[0]) || trim($shift[0]) === '') {
                continue;
            }

            $roleRaw = (string) ($staff->role ?? '');
            $category = $this->roleCategory($roleRaw);

            $row = [
                'staff' => $staff,
                'shift' => $shift,
                'role' => $roleRaw,
                'role_display' => $roleRaw !== '' ? $roleRaw : '—',
                'category' => $category,
            ];

            if ($isToday) {
                $att = $attendancesByStaffId->get($staff->id);
                $row['live_status'] = $this->resolveMealLiveStatus($att, true, $shift[0] ?? null, $meal);
            } else {
                $row['live_status'] = null;
            }

            $assignments[] = $row;
        }

        usort($assignments, function (array $a, array $b): int {
            $order = ['kitchen' => 0, 'hall' => 1, 'other' => 2];
            $ca = $order[$a['category']] ?? 2;
            $cb = $order[$b['category']] ?? 2;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            $r = strcasecmp((string) $a['role'], (string) $b['role']);
            if ($r !== 0) {
                return $r;
            }

            /** @var Staff $sa */
            $sa = $a['staff'];
            /** @var Staff $sb */
            $sb = $b['staff'];

            return strcasecmp((string) $sa->name, (string) $sb->name);
        });

        $counts = ['kitchen' => 0, 'hall' => 0, 'other' => 0];
        foreach ($assignments as $row) {
            $cat = $row['category'];
            if (isset($counts[$cat])) {
                $counts[$cat]++;
            }
        }

        $liveExtras = [];
        if ($isToday) {
            $scheduledIds = collect($assignments)->map(fn (array $r) => $r['staff']->id)->all();
            foreach ($staffs as $staff) {
                if (in_array($staff->id, $scheduledIds, true)) {
                    continue;
                }
                $att = $attendancesByStaffId->get($staff->id);
                $st = $this->resolveMealLiveStatus($att, false, null, $meal);
                if ($st !== 'extra') {
                    continue;
                }
                $roleRaw = (string) ($staff->role ?? '');
                $liveExtras[] = [
                    'staff' => $staff,
                    'role_display' => $roleRaw !== '' ? $roleRaw : '—',
                    'category' => $this->roleCategory($roleRaw),
                    'in_at' => $meal === 'lunch' ? $att?->lunch_in_at : $att?->dinner_in_at,
                ];
            }
        }

        return [
            'assignments' => $assignments,
            'counts' => $counts,
            'live_extras' => $liveExtras,
        ];
    }

    /**
     * clocked | extra | late | future | none
     */
    private function resolveMealLiveStatus(?Attendance $att, bool $hasScheduledShift, ?string $plannedStartTime, string $meal): string
    {
        $inAt = $meal === 'lunch' ? $att?->lunch_in_at : $att?->dinner_in_at;

        if ($inAt && ! $hasScheduledShift) {
            return 'extra';
        }

        if ($inAt && $hasScheduledShift) {
            return 'clocked';
        }

        if (! $hasScheduledShift) {
            return 'none';
        }

        /** @var AttendanceStatusResolver $resolver */
        $resolver = app(AttendanceStatusResolver::class);

        return $resolver->resolveMealStatus(
            BusinessDate::current(),
            $plannedStartTime,
            $inAt,
        );
    }

    /**
     * 表示・集計用のロール区分（DBは変更しない）。
     */
    private function roleCategory(string $role): string
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

    /**
     * Blade 用: ステータス → 絵文字インジケーター。
     */
    public static function liveStatusIcon(string $status): string
    {
        return match ($status) {
            'clocked' => '🟢',
            'extra' => '🆘',
            'late' => '🔴',
            'future' => '⚪',
            default => '',
        };
    }
}
