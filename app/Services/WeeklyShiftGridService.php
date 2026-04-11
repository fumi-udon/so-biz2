<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Staff;
use App\Support\BusinessDate;
use Illuminate\Support\Collection;

/**
 * 週間シフト表・本日人員ブロック用の共通集計（Filament / フロント welcome 共用）。
 */
class WeeklyShiftGridService
{
    /**
     * @deprecated Utiliser {@see self::translatedDayLabels()} pour l’affichage localisé.
     *
     * @var array<string, string>
     */
    public const DAY_LABELS = [
        'monday' => 'Lun',
        'tuesday' => 'Mar',
        'wednesday' => 'Mer',
        'thursday' => 'Jeu',
        'friday' => 'Ven',
        'saturday' => 'Sam',
        'sunday' => 'Dim',
    ];

    /**
     * @deprecated Utiliser {@see self::translatedDayShortLabels()}.
     *
     * @var array<string, string>
     */
    public const DAY_SHORT_LABELS = [
        'monday' => 'Lun',
        'tuesday' => 'Mar',
        'wednesday' => 'Mer',
        'thursday' => 'Jeu',
        'friday' => 'Ven',
        'saturday' => 'Sam',
        'sunday' => 'Dim',
    ];

    /**
     * @return array<string, string>
     */
    public static function translatedDayLabels(): array
    {
        return [
            'monday' => __('hq.day_short_mon', [], 'fr'),
            'tuesday' => __('hq.day_short_tue', [], 'fr'),
            'wednesday' => __('hq.day_short_wed', [], 'fr'),
            'thursday' => __('hq.day_short_thu', [], 'fr'),
            'friday' => __('hq.day_short_fri', [], 'fr'),
            'saturday' => __('hq.day_short_sat', [], 'fr'),
            'sunday' => __('hq.day_short_sun', [], 'fr'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function translatedDayShortLabels(): array
    {
        return self::translatedDayLabels();
    }

    /** @var list<string> */
    public const DAY_KEYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    public function __construct(
        private AttendanceStatusResolver $resolver,
    ) {}

    /**
     * @return array{
     *     staffs: Collection<int, Staff>,
     *     shiftGrid: array<string, array{lunch: array, dinner: array}>,
     *     dayLabels: array<string, string>,
     *     dayShortLabels: array<string, string>,
     *     todayDayKey: string,
     *     attendancesToday: Collection<int, Attendance>,
     *     liveByStaff: array<int, array{lunch: string, dinner: string}>
     * }
     */
    public function build(): array
    {
        $staffs = Staff::query()
            ->where('is_active', true)
            ->with('jobLevel')
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
            'dayLabels' => self::translatedDayLabels(),
            'dayShortLabels' => self::translatedDayShortLabels(),
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
            $ta = $this->shiftSlotStartMinutes($a['shift'] ?? null);
            $tb = $this->shiftSlotStartMinutes($b['shift'] ?? null);
            if ($ta !== $tb) {
                return $ta <=> $tb;
            }

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

        usort($liveExtras, function (array $a, array $b): int {
            $ta = $a['in_at']?->getTimestamp() ?? 0;
            $tb = $b['in_at']?->getTimestamp() ?? 0;

            return $ta <=> $tb;
        });

        return [
            'assignments' => $assignments,
            'counts' => $counts,
            'live_extras' => $liveExtras,
        ];
    }

    /**
     * シフト先頭時刻（slot[0]）を分単位にし、早い順ソート用。
     */
    private function shiftSlotStartMinutes(?array $shift): int
    {
        if (! is_array($shift) || ! isset($shift[0]) || ! is_string($shift[0])) {
            return 24 * 60 + 1;
        }

        $raw = trim($shift[0]);
        if ($raw === '') {
            return 24 * 60 + 1;
        }

        if (preg_match('/^(\d{1,2}):(\d{2})/', $raw, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        if (preg_match('/^(\d{1,2})h(\d{2})$/i', $raw, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        return 24 * 60 + 1;
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

        return $this->resolver->resolveMealStatus(
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
        $hallNeedles = ['hall', 'waiter', 'waitress', 'service', 'salle', 'floor', 'serveur', 'serveuse'];

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

    /**
     * welcome 用: Filament アイコンなしでロールを示す絵文字（weekly-shift-staff-role-icon と同ロジック）。
     */
    public static function staffRoleEmoji(Staff $staff): string
    {
        if ($staff->is_manager ?? false) {
            return '📋';
        }
        $r = strtolower((string) ($staff->role ?? ''));
        $jl = strtolower((string) ($staff->jobLevel?->name ?? ''));
        $hay = $r.' '.$jl;
        if ($hay !== ' ' && (
            str_contains($hay, 'kit') || str_contains($hay, 'cuis') || str_contains($hay, 'cook')
            || str_contains($hay, 'kitchen') || str_contains($hay, '調理')
        )) {
            return '🔥';
        }
        if (str_contains($hay, 'hall') || str_contains($hay, 'salle') || str_contains($hay, 'serve') || str_contains($hay, 'ホール')) {
            return '👥';
        }
        if (str_contains($hay, 'manage') || str_contains($hay, 'chef') || str_contains($hay, 'マネ') || str_contains($hay, 'dir')) {
            return '📋';
        }

        return '👤';
    }
}
