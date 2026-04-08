<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AttendanceEditLog;
use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\RoutineTask;
use App\Models\RoutineTaskLog;
use App\Models\Staff;
use App\Models\StaffTip;
use App\Services\AttendanceStatusResolver;
use App\Support\AbsenceScope;
use App\Support\AttendanceLateCalculator;
use App\Support\BusinessDate;
use App\Support\FixedShiftSchedule;
use App\Support\InventorySettingOptions;
use App\Support\StoreHolidaySetting;
use App\Support\TipAttendanceScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MyPageController extends Controller
{
    private const ROLE_ORDER = [
        'kitchen' => 0,
        'hall' => 1,
        'other' => 2,
    ];

    /**
     * @return list<string>
     */
    protected function orderInventoryTimingKeys(Collection $keys): array
    {
        $master = array_keys(InventorySettingOptions::timingForSelect());
        $present = $keys->filter(static fn ($k): bool => $k !== null && $k !== '')->unique()->values()->all();
        $ordered = [];
        foreach ($master as $k) {
            if (in_array($k, $present, true)) {
                $ordered[] = $k;
            }
        }
        foreach ($present as $k) {
            if (! in_array($k, $ordered, true)) {
                $ordered[] = $k;
            }
        }

        return $ordered;
    }

    public function index(Request $request, AttendanceStatusResolver $statusResolver): View
    {
        $staffList = Staff::query()
            ->where('is_active', true)
            ->get();
        $staffList = $staffList->sort(function (Staff $a, Staff $b): int {
            $ca = self::ROLE_ORDER[$this->roleCategory((string) ($a->role ?? ''))] ?? 2;
            $cb = self::ROLE_ORDER[$this->roleCategory((string) ($b->role ?? ''))] ?? 2;
            if ($ca !== $cb) {
                return $ca <=> $cb;
            }

            return strcasecmp((string) $a->name, (string) $b->name);
        })->values();

        $staffId = $request->integer('staff_id') ?: null;
        $authorizedStaffId = $request->session()->get('mypage_staff_id');
        $staff = ($staffId !== null && $authorizedStaffId !== null && (int) $authorizedStaffId === $staffId)
            ? Staff::query()->where('id', $staffId)->where('is_active', true)->first()
            : null;

        $businessDate = BusinessDate::current();
        $dateString = $businessDate->toDateString();
        $dayKey = strtolower($businessDate->englishDayOfWeek);

        $routineTasks = collect();
        $inventoryItems = collect();
        $routineLogIds = collect();
        $inventoryTimingRows = [];
        $motivationLevel = 1;
        $todayClockInLabel = null;
        $todayAttendance = null;
        $roleLabel = null;
        $roleColor = 'gray';
        $lunchScheduledStart = null;
        $dinnerScheduledStart = null;
        $lunchStatus = 'none';
        $dinnerStatus = 'none';
        $lunchInTime = null;
        $dinnerInTime = null;
        $lunchLate = false;
        $dinnerLate = false;
        $totalTipAmount = 0.0;
        $recentTips = collect();
        $tipHistory = collect();
        $todayTipAmount = 0.0;
        $last5Tips = collect();
        $tipDailyBreakdown = collect();
        $tipRecent3Total = 0.0;
        $tipRecentNonZero3 = collect();
        $monthLateCount = 0;
        $monthAbsentCount = 0;
        $monthTipWinCount = 0;
        $monthDamageCount = 0;
        $monthLateDates = collect();
        $monthAbsentDates = collect();
        $monthAttendances = collect();

        if ($staff) {
            $completionCount = RoutineTaskLog::query()
                ->where('completed_by_staff_id', $staff->id)
                ->count();
            $motivationLevel = max(1, intdiv($completionCount, 10) + 1);

            $attendance = Attendance::query()
                ->where('staff_id', $staff->id)
                ->whereDate('date', $dateString)
                ->first();
            $todayAttendance = $attendance;

            $lunchScheduledStart = $attendance?->scheduled_in_at
                ? $attendance->scheduled_in_at->format('H:i')
                : FixedShiftSchedule::start($staff, $dayKey, 'lunch');
            $dinnerScheduledStart = $attendance?->scheduled_dinner_at
                ? $attendance->scheduled_dinner_at->format('H:i')
                : FixedShiftSchedule::start($staff, $dayKey, 'dinner');
            $lunchStatus = $statusResolver->resolveMealStatus($businessDate, $lunchScheduledStart, $attendance?->lunch_in_at);
            $dinnerStatus = $statusResolver->resolveMealStatus($businessDate, $dinnerScheduledStart, $attendance?->dinner_in_at);
            $lunchInTime = $attendance?->lunch_in_at?->format('H:i');
            $dinnerInTime = $attendance?->dinner_in_at?->format('H:i');

            // 遅刻表示は保存済みスナップショットのみ（DB の late_minutes と同一ロジック）
            $lunchLate = $attendance !== null
                && AttendanceLateCalculator::lateMinutesForMeal($attendance->lunch_in_at, $attendance->scheduled_in_at) > 0;
            $dinnerLate = $attendance !== null
                && AttendanceLateCalculator::lateMinutesForMeal($attendance->dinner_in_at, $attendance->scheduled_dinner_at) > 0;

            $roleCategory = $this->roleCategory((string) ($staff->role ?? ''));
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

            if ($attendance) {
                $first = collect([
                    $attendance->lunch_in_at,
                    $attendance->dinner_in_at,
                ])
                    ->filter()
                    ->sortBy(fn ($t) => $t->getTimestamp())
                    ->first();
                if ($first !== null) {
                    $todayClockInLabel = $first->format('H:i');
                }
            }

            $routineTasks = RoutineTask::query()
                ->where('assigned_staff_id', $staff->id)
                ->where('shop_id', $staff->shop_id)
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();

            $routineLogIds = RoutineTaskLog::query()
                ->whereIn('routine_task_id', $routineTasks->pluck('id'))
                ->whereDate('date', $dateString)
                ->pluck('routine_task_id');

            $inventoryItems = InventoryItem::query()
                ->where('assigned_staff_id', $staff->id)
                ->where('shop_id', $staff->shop_id)
                ->where('is_active', true)
                ->orderBy('category')
                ->orderBy('name')
                ->get();

            $records = InventoryRecord::query()
                ->whereIn('inventory_item_id', $inventoryItems->pluck('id'))
                ->whereDate('date', $dateString)
                ->get()
                ->keyBy('inventory_item_id');

            $timingLabels = InventorySettingOptions::timingForSelect();
            $byTiming = $inventoryItems->groupBy(fn (InventoryItem $item): string => (string) ($item->timing ?? ''));

            foreach ($this->orderInventoryTimingKeys($byTiming->keys()) as $timingKey) {
                $itemsInTiming = $byTiming->get($timingKey, collect());
                if ($itemsInTiming->isEmpty()) {
                    continue;
                }
                $total = $itemsInTiming->count();
                $filled = 0;
                foreach ($itemsInTiming as $item) {
                    $v = $records->get($item->id)?->value;
                    if ($v !== null && $v !== '') {
                        $filled++;
                    }
                }
                $inventoryTimingRows[] = [
                    'timing_key' => $timingKey,
                    'label' => $timingLabels[$timingKey] ?? ($timingKey !== '' ? $timingKey : '—'),
                    'complete' => $total > 0 && $filled >= $total,
                    'total' => $total,
                    'filled' => $filled,
                    'portal_url' => route('inventory.input', ['timing' => $timingKey, 'staff_id' => $staff->id]),
                ];
            }

            $totalTipAmount = (float) StaffTip::query()
                ->where('staff_id', $staff->id)
                ->sum('amount');

            $recentTips = StaffTip::query()
                ->where('staff_id', $staff->id)
                ->with('dailyTip')
                ->orderByDesc('id')
                ->limit(3)
                ->get();

            $tipHistoryRaw = StaffTip::query()
                ->where('daily_tip_distributions.staff_id', $staff->id)
                ->join('daily_tips', 'daily_tip_distributions.daily_tip_id', '=', 'daily_tips.id')
                ->orderByDesc('daily_tips.business_date')
                ->orderByDesc('daily_tip_distributions.id')
                ->select('daily_tip_distributions.*')
                ->limit(10)
                ->with('dailyTip')
                ->get();

            $previous = null;
            $tipHistory = $tipHistoryRaw->map(function (StaffTip $tip, int $index) use (&$previous): array {
                $amount = (float) $tip->amount;
                $delta = $previous === null ? 0.0 : ($amount - $previous);
                $previous = $amount;

                return [
                    'date' => optional($tip->dailyTip?->business_date)?->format('m/d') ?? '—',
                    'amount' => $amount,
                    'note' => (string) ($tip->note ?? ''),
                    'is_new' => $index === 0,
                    'delta' => $delta,
                ];
            });

            $last5Tips = StaffTip::query()
                ->where('staff_id', $staff->id)
                ->with('dailyTip')
                ->whereHas('dailyTip', function ($q) use ($businessDate): void {
                    $q->whereBetween('business_date', [
                        $businessDate->copy()->subDays(4)->toDateString(),
                        $businessDate->toDateString(),
                    ]);
                })
                ->get()
                ->groupBy(function (StaffTip $tip): string {
                    return optional($tip->dailyTip?->business_date)?->toDateString() ?? '';
                })
                ->filter(fn ($rows, $date): bool => $date !== '')
                ->map(function (Collection $rows, string $date): array {
                    /** @var StaffTip $latest */
                    $latest = $rows->sortByDesc('id')->first();

                    return [
                        'date_key' => $date,
                        'date' => Carbon::parse($date)->format('m/d'),
                        'amount' => (float) $rows->sum('amount'),
                        'note' => (string) ($latest?->note ?? ''),
                    ];
                })
                ->sortByDesc(fn (array $row): string => (string) ($row['date_key'] ?? ''))
                ->map(function (array $row): array {
                    unset($row['date_key']);

                    return $row;
                })
                ->values();

            $tipDailyRawByDate = StaffTip::query()
                ->where('staff_id', $staff->id)
                ->with('dailyTip')
                ->whereHas('dailyTip')
                ->get()
                ->groupBy(function (StaffTip $tip): string {
                    return optional($tip->dailyTip?->business_date)?->toDateString() ?? '';
                })
                ->filter(fn ($rows, $date): bool => $date !== '');

            $tipDailyBreakdown = collect(range(0, 2))->map(function (int $offset) use ($businessDate, $tipDailyRawByDate): array {
                $date = $businessDate->copy()->subDays($offset)->toDateString();
                /** @var Collection<int, StaffTip> $rows */
                $rows = $tipDailyRawByDate->get($date, collect());

                $lunch = (float) $rows
                    ->filter(function (StaffTip $tip): bool {
                        $shift = strtolower((string) ($tip->dailyTip?->shift ?? ''));

                        return str_contains($shift, 'lunch');
                    })
                    ->sum('amount');

                $dinner = (float) $rows
                    ->filter(function (StaffTip $tip): bool {
                        $shift = strtolower((string) ($tip->dailyTip?->shift ?? ''));

                        return str_contains($shift, 'dinner');
                    })
                    ->sum('amount');

                $total = (float) ($lunch + $dinner);

                return [
                    'date_key' => $date,
                    'date' => Carbon::parse($date)->format('m/d'),
                    'lunch' => $lunch,
                    'dinner' => $dinner,
                    'total' => $total,
                ];
            })->values();

            $tipRecent3Total = (float) $tipDailyBreakdown->sum('total');
            $todayTipAmount = (float) ($tipDailyBreakdown->first()['total'] ?? 0.0);

            $tipRecentNonZero3 = StaffTip::query()
                ->where('staff_id', $staff->id)
                ->with('dailyTip')
                ->whereHas('dailyTip')
                ->whereHas('dailyTip', function ($query) use ($businessDate) {
                    // 3日前より前の日付
                    $query->where('business_date', '<', $businessDate->copy()->subDays(3)->toDateString());
                })
                ->get()
                ->groupBy(function (StaffTip $tip): string {
                    return optional($tip->dailyTip?->business_date)?->toDateString() ?? '';
                })
                ->filter(fn ($rows, $date): bool => $date !== '')
                ->map(function (Collection $rows, string $date): array {
                    $lunch = (float) $rows
                        ->filter(function (StaffTip $tip): bool {
                            $shift = strtolower((string) ($tip->dailyTip?->shift ?? ''));

                            return str_contains($shift, 'lunch');
                        })
                        ->sum('amount');

                    $dinner = (float) $rows
                        ->filter(function (StaffTip $tip): bool {
                            $shift = strtolower((string) ($tip->dailyTip?->shift ?? ''));

                            return str_contains($shift, 'dinner');
                        })
                        ->sum('amount');

                    $total = (float) ($lunch + $dinner);

                    return [
                        'date_key' => $date,
                        'date' => Carbon::parse($date)->format('m/d'),
                        'lunch' => $lunch,
                        'dinner' => $dinner,
                        'total' => $total,
                    ];
                })
                ->filter(fn (array $row): bool => ((float) ($row['total'] ?? 0.0)) >= 0.1)
                ->sortByDesc(fn (array $row): string => (string) ($row['date_key'] ?? ''))
                ->take(5)
                ->map(function (array $row): array {
                    unset($row['date_key']);

                    return $row;
                })
                ->values();

            $monthStart = $businessDate->copy()->startOfMonth()->toDateString();
            $monthEnd = $businessDate->copy()->toDateString();
            $monthlyAttendances = Attendance::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('date', [$monthStart, $monthEnd])
                ->orderBy('date')
                ->get();
            $monthAttendances = $monthlyAttendances;

            $monthLateDates = $monthlyAttendances
                ->filter(fn (Attendance $row): bool => (int) ($row->late_minutes ?? 0) > 0)
                ->map(function (Attendance $row): string {
                    return Carbon::parse($row->date)->format('m/d').' retard';
                })
                ->values();
            $monthLateCount = $monthLateDates->count();

            // AbsenceScope: 休業日・出勤実績・確定欠勤（StaffAbsence）に基づくリスト
            $attendanceByDate = $monthlyAttendances->keyBy(fn (Attendance $r) => Carbon::parse($r->date)->toDateString());
            $holidaySet = StoreHolidaySetting::dateSet();
            $absenceMap = AbsenceScope::loadAbsenceMapForStaffInRange([$staff->id], $monthStart, $monthEnd);
            $monthAbsentDates = collect();
            $cursor = Carbon::parse($monthStart);
            $cursorEnd = Carbon::parse($monthEnd);
            while ($cursor->lte($cursorEnd)) {
                $d = $cursor->toDateString();
                $rowForDay = $attendanceByDate->get($d);
                $hasAbs = isset($absenceMap[$staff->id][$d]);
                if (AbsenceScope::resolveDay($d, $rowForDay, $holidaySet, $hasAbs) === AbsenceScope::STATUS_ABSENT) {
                    $monthAbsentDates->push($cursor->format('m/d').' absence');
                }
                $cursor->addDay();
            }
            $monthAbsentDates = $monthAbsentDates->values();
            $monthAbsentCount = $monthAbsentDates->count();
            $monthDamageCount = $monthLateCount;
            // TipAttendanceScope（打刻 + 申請 + 非剥奪）と同一
            $monthTipWinCount = $monthlyAttendances->reduce(
                function (int $carry, Attendance $row): int {
                    $lunchWin = TipAttendanceScope::lunchEligible($row);
                    $dinnerWin = TipAttendanceScope::dinnerEligible($row);

                    return $carry + ($lunchWin ? 1 : 0) + ($dinnerWin ? 1 : 0);
                },
                0,
            );
        }

        $routinesPendingCount = 0;
        if ($staff && $routineTasks->isNotEmpty()) {
            foreach ($routineTasks as $task) {
                if (! $routineLogIds->contains($task->id)) {
                    $routinesPendingCount++;
                }
            }
        }
        $routinesAllComplete = $staff && ($routineTasks->isEmpty() || $routinesPendingCount === 0);

        return view('mypage.index', [
            'staffList' => $staffList,
            'staff' => $staff,
            'dateString' => $dateString,
            'routineTasks' => $routineTasks,
            'routineLogIds' => $routineLogIds,
            'inventoryItems' => $inventoryItems,
            'inventoryTimingRows' => $inventoryTimingRows,
            'motivationLevel' => $motivationLevel,
            'todayClockInLabel' => $todayClockInLabel,
            'routinesAllComplete' => $routinesAllComplete,
            'routinesPendingCount' => $routinesPendingCount,
            'businessDate' => $businessDate,
            'todayAttendance' => $todayAttendance,
            'roleLabel' => $roleLabel,
            'roleColor' => $roleColor,
            'lunchScheduledStart' => $lunchScheduledStart,
            'dinnerScheduledStart' => $dinnerScheduledStart,
            'lunchStatus' => $lunchStatus,
            'dinnerStatus' => $dinnerStatus,
            'lunchInTime' => $lunchInTime,
            'dinnerInTime' => $dinnerInTime,
            'lunchLate' => $lunchLate,
            'dinnerLate' => $dinnerLate,
            'totalTipAmount' => $totalTipAmount,
            'recentTips' => $recentTips,
            'tipHistory' => $tipHistory,
            'todayTipAmount' => $todayTipAmount,
            'last5Tips' => $last5Tips,
            'tipDailyBreakdown' => $tipDailyBreakdown,
            'tipRecent3Total' => $tipRecent3Total,
            'tipRecentNonZero3' => $tipRecentNonZero3,
            'monthLateCount' => $monthLateCount,
            'monthAbsentCount' => $monthAbsentCount,
            'monthTipWinCount' => $monthTipWinCount,
            'monthDamageCount' => $monthDamageCount,
            'monthLateDates' => $monthLateDates,
            'monthAbsentDates' => $monthAbsentDates,
            'monthAttendances' => $monthAttendances,
            'statusResolver' => $statusResolver,
        ]);
    }

    public function autoLogout(Request $request): JsonResponse
    {
        // Mon espace の本人確認のみ解除する。session()->invalidate() すると pagehide の fetch と
        // トップ（welcome）の @csrf がレースし、次の POST（mypage.open）が 419 になりやすい。
        $request->session()->forget('mypage_staff_id');

        return response()->json(['ok' => true]);
    }

    public function openByPin(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return back()->with('error', 'Personnel introuvable.');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return back()->with('error', 'Aucun PIN defini pour ce personnel.');
        }

        $pinKey = 'pin-attempt:staff:'.$staff->id.':'.(request()->ip() ?? 'unknown');

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return back()->with('error', 'Trop de tentatives PIN incorrectes. Veuillez patienter 1 minute.');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            RateLimiter::hit($pinKey, 60);

            return back()->with('error', 'Code PIN incorrect.');
        }

        RateLimiter::clear($pinKey);
        $request->session()->put('mypage_staff_id', $staff->id);

        return redirect()->route('mypage.index', ['staff_id' => $staff->id]);
    }

    public function reauthenticate(Request $request): RedirectResponse
    {
        $request->session()->forget('mypage_staff_id');

        return redirect()
            ->route('home', ['open_mypage' => 1])
            ->with('status', 'Saisissez le PIN pour acceder a Mon espace. Changement de personnel possible a tout moment.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
            'routine_task' => ['nullable', 'array'],
            'inventory_val' => ['nullable', 'array'],
            'inventory_val.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return redirect()
                ->route('mypage.index')
                ->with('error', 'Personnel introuvable.');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->route('mypage.index', ['staff_id' => $staff->id])
                ->with('error', 'PIN non configure.');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            return redirect()
                ->route('mypage.index', ['staff_id' => $staff->id])
                ->withInput($request->except('pin_code'))
                ->with('error', 'Code PIN incorrect.');
        }

        $dateString = BusinessDate::toDateString();
        $now = now();

        $routineInput = $validated['routine_task'] ?? [];
        $invInput = $validated['inventory_val'] ?? [];
        if (! is_array($routineInput)) {
            $routineInput = [];
        }
        if (! is_array($invInput)) {
            $invInput = [];
        }

        $processInventory = $request->has('inventory_val');

        $itemsById = $processInventory
            ? InventoryItem::query()
                ->where('assigned_staff_id', $staff->id)
                ->where('shop_id', $staff->shop_id)
                ->where('is_active', true)
                ->get()
                ->keyBy('id')
            : collect();

        if ($processInventory) {
            foreach ($invInput as $itemId => $raw) {
                $itemId = (int) $itemId;
                if ($itemId === 0) {
                    continue;
                }
                $item = $itemsById->get($itemId);
                if (! $item) {
                    throw ValidationException::withMessages([
                        'inventory_val' => 'Des articles d\'inventaire invalides sont inclus.',
                    ]);
                }
                if ($raw === null || $raw === '') {
                    continue;
                }
                $type = $item->input_type ?? 'number';
                if ($type === 'number' && ! is_numeric($raw)) {
                    throw ValidationException::withMessages([
                        "inventory_val.$itemId" => 'Veuillez saisir une valeur numerique.',
                    ]);
                }
                if ($type === 'select') {
                    $opts = $item->options ?? [];
                    if (! is_array($opts) || ! in_array($raw, $opts, true)) {
                        throw ValidationException::withMessages([
                            "inventory_val.$itemId" => 'Veuillez choisir une option valide.',
                        ]);
                    }
                }
            }
        }

        DB::transaction(function () use ($staff, $dateString, $now, $routineInput, $invInput, $itemsById, $processInventory): void {
            $allowedRoutineIds = RoutineTask::query()
                ->where('assigned_staff_id', $staff->id)
                ->where('shop_id', $staff->shop_id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            foreach ($allowedRoutineIds as $rid) {
                $routineVal = $routineInput[$rid] ?? $routineInput[(string) $rid] ?? null;
                $checked = $routineVal !== null && $routineVal !== '' && $routineVal !== false && $routineVal !== '0';
                if (! $checked) {
                    RoutineTaskLog::query()
                        ->where('routine_task_id', $rid)
                        ->whereDate('date', $dateString)
                        ->delete();

                    continue;
                }

                RoutineTaskLog::query()->updateOrCreate(
                    [
                        'routine_task_id' => $rid,
                        'date' => $dateString,
                    ],
                    [
                        'completed_by_staff_id' => $staff->id,
                        'completed_at' => $now,
                    ],
                );
            }

            if ($processInventory) {
                $allowedItemIds = $itemsById->keys()->all();

                foreach ($allowedItemIds as $iid) {
                    $keyPresent = array_key_exists($iid, $invInput) || array_key_exists((string) $iid, $invInput);
                    $raw = $invInput[$iid] ?? $invInput[(string) $iid] ?? null;

                    if (! $keyPresent) {
                        InventoryRecord::query()
                            ->where('inventory_item_id', $iid)
                            ->whereDate('date', $dateString)
                            ->delete();

                        continue;
                    }

                    if ($raw === null || $raw === '') {
                        InventoryRecord::query()
                            ->where('inventory_item_id', $iid)
                            ->whereDate('date', $dateString)
                            ->delete();

                        continue;
                    }

                    InventoryRecord::query()->updateOrCreate(
                        [
                            'inventory_item_id' => $iid,
                            'date' => $dateString,
                        ],
                        [
                            'value' => $raw,
                            'recorded_by_staff_id' => $staff->id,
                        ],
                    );
                }
            }
        });

        return redirect()
            ->route('mypage.index', ['staff_id' => $staff->id])
            ->with('status', 'Enregistre avec succes.');
    }

    public function attendance(Request $request): View
    {
        $staffList = Staff::query()
            ->where('is_active', true)
            ->with('jobLevel')
            ->orderBy('name')
            ->get();

        $staffId = $request->integer('staff_id') ?: null;
        $staff = $staffId ? Staff::query()->where('id', $staffId)->where('is_active', true)->first() : null;

        $monthParam = $request->input('month');
        $monthStart = Carbon::parse(is_string($monthParam) && preg_match('/^\d{4}-\d{2}$/', $monthParam)
            ? $monthParam.'-01'
            : BusinessDate::current()->format('Y-m-01'))->startOfMonth();

        $monthAttendances = collect();
        $weekMinutes = 0;
        $monthMinutes = 0;
        $monthLateCount = 0;

        if ($staff) {
            $monthAttendances = Attendance::query()
                ->where('staff_id', $staff->id)
                ->whereYear('date', $monthStart->year)
                ->whereMonth('date', $monthStart->month)
                ->orderBy('date')
                ->get();

            $businessCurrent = BusinessDate::current();
            $weekStart = $businessCurrent->copy()->startOfWeek(Carbon::MONDAY);
            $weekEnd = $businessCurrent->copy()->endOfWeek(Carbon::SUNDAY);

            $weekRows = Attendance::query()
                ->where('staff_id', $staff->id)
                ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
                ->get();

            foreach ($weekRows as $row) {
                $weekMinutes += $row->calculateTotalMinutes() ?? 0;
            }

            foreach ($monthAttendances as $row) {
                $monthMinutes += $row->calculateTotalMinutes() ?? 0;
                if (($row->late_minutes ?? 0) > 0) {
                    $monthLateCount++;
                }
            }
        }

        $editLogs = $staff
            ? AttendanceEditLog::query()
                ->where('target_staff_id', $staff->id)
                ->where('created_at', '>=', now()->subMonth())
                ->with([
                    'editorStaff:id,name',
                    'attendance:id,date',
                ])
                ->orderByDesc('created_at')
                ->paginate(10)
                ->withQueryString()
            : null;

        return view('mypage.attendance', [
            'staffList' => $staffList,
            'staff' => $staff,
            'monthStart' => $monthStart,
            'monthAttendances' => $monthAttendances,
            'attendances' => $monthAttendances,
            'weekMinutes' => $weekMinutes,
            'monthMinutes' => $monthMinutes,
            'monthLateCount' => $monthLateCount,
            'editLogs' => $editLogs,
        ]);
    }

    public function updateAttendance(Request $request): RedirectResponse
    {
        return redirect()
            ->route('mypage.attendance', array_filter([
                'staff_id' => $request->input('staff_id'),
                'month' => $request->input('month'),
            ]))
            ->with('error', 'La modification depuis Mon espace est désactivée. Contactez un manager (admin Filament).');
    }

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
     * Mon espace からの勤怠編集は廃止（Filament のみ）。互換のためルートは残す。
     */
    public function authorizeEdit(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Edition desactivee. Utilisez l\'admin Filament (manager).',
        ], 403);
    }

    /**
     * Mon espace からの勤怠編集は廃止（Filament のみ）。互換のためルートは残す。
     */
    public function patchAttendance(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'message' => 'Edition desactivee. Utilisez l\'admin Filament (manager).',
        ], 403);
    }
}
