<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\DailyTipDistribution;
use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\RoutineTask;
use App\Models\RoutineTaskLog;
use App\Models\Staff;
use App\Models\StaffTip;
use App\Services\AttendanceStatusResolver;
use App\Support\BusinessDate;
use App\Support\InventorySettingOptions;
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
        $staff = $staffId ? Staff::query()->where('id', $staffId)->where('is_active', true)->first() : null;

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

            $lunchScheduledStart = $this->scheduledStartFromFixedShifts($staff, $dayKey, 'lunch');
            $dinnerScheduledStart = $this->scheduledStartFromFixedShifts($staff, $dayKey, 'dinner');
            $lunchStatus = $statusResolver->resolveMealStatus($businessDate, $lunchScheduledStart, $attendance?->lunch_in_at);
            $dinnerStatus = $statusResolver->resolveMealStatus($businessDate, $dinnerScheduledStart, $attendance?->dinner_in_at);
            $lunchInTime = $attendance?->lunch_in_at?->format('H:i');
            $dinnerInTime = $attendance?->dinner_in_at?->format('H:i');

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
                ->where('staff_id', $staff->id)
                ->with('dailyTip')
                ->get()
                ->sort(function (StaffTip $a, StaffTip $b): int {
                    $ad = optional($a->dailyTip?->business_date)?->getTimestamp() ?? 0;
                    $bd = optional($b->dailyTip?->business_date)?->getTimestamp() ?? 0;
                    if ($ad !== $bd) {
                        return $bd <=> $ad;
                    }

                    return $b->id <=> $a->id;
                })
                ->take(10)
                ->values();

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
                ->take(3)
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

            $monthLateDates = $monthlyAttendances
                ->filter(fn (Attendance $row): bool => (int) ($row->late_minutes ?? 0) > 0)
                ->map(function (Attendance $row): string {
                    return Carbon::parse($row->date)->format('m/d').' 遅刻';
                })
                ->values();
            $monthLateCount = $monthLateDates->count();

            $monthAbsentDates = $monthlyAttendances
                ->filter(function (Attendance $row) use ($staff): bool {
                    $workDate = Carbon::parse($row->date);
                    $dayKeyForRow = strtolower($workDate->englishDayOfWeek);
                    $hasPlannedShift = filled($this->scheduledStartFromFixedShifts($staff, $dayKeyForRow, 'lunch'))
                        || filled($this->scheduledStartFromFixedShifts($staff, $dayKeyForRow, 'dinner'));
                    $hasAnyClockIn = $row->lunch_in_at !== null || $row->dinner_in_at !== null;

                    return $hasPlannedShift && ! $hasAnyClockIn;
                })
                ->map(function (Attendance $row): string {
                    return Carbon::parse($row->date)->format('m/d').' 欠勤';
                })
                ->values();
            $monthAbsentCount = $monthAbsentDates->count();
            $monthDamageCount = $monthLateCount;
            $monthTipWinCount = $monthlyAttendances->reduce(
                function (int $carry, Attendance $row): int {
                    return $carry
                        + ((bool) ($row->is_lunch_tip_applied ?? false) ? 1 : 0)
                        + ((bool) ($row->is_dinner_tip_applied ?? false) ? 1 : 0);
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
            'statusResolver' => $statusResolver,
        ]);
    }

    public function autoLogout(Request $request): JsonResponse
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

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
            return back()->with('error', 'スタッフが見つかりません。');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return back()->with('error', 'このスタッフの PIN が未設定です。');
        }

        $pinKey = 'pin-attempt:'.$staff->id;

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return back()->with('error', 'PINの入力を複数回間違えました。1分間お待ちください。');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            RateLimiter::hit($pinKey, 60);

            return back()->with('error', 'PIN が正しくありません。');
        }

        RateLimiter::clear($pinKey);

        return redirect()->route('mypage.index', ['staff_id' => $staff->id]);
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
                ->with('error', 'スタッフが見つかりません。');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->route('mypage.index', ['staff_id' => $staff->id])
                ->with('error', 'PIN が設定されていません。');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            return redirect()
                ->route('mypage.index', ['staff_id' => $staff->id])
                ->withInput($request->except('pin_code'))
                ->with('error', 'PIN が正しくありません。');
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
                        'inventory_val' => '不正な棚卸し品目が含まれています。',
                    ]);
                }
                if ($raw === null || $raw === '') {
                    continue;
                }
                $type = $item->input_type ?? 'number';
                if ($type === 'number' && ! is_numeric($raw)) {
                    throw ValidationException::withMessages([
                        "inventory_val.$itemId" => '数値で入力してください。',
                    ]);
                }
                if ($type === 'select') {
                    $opts = $item->options ?? [];
                    if (! is_array($opts) || ! in_array($raw, $opts, true)) {
                        throw ValidationException::withMessages([
                            "inventory_val.$itemId" => '選択肢から選んでください。',
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
            ->with('status', '保存しました。');
    }

    public function attendance(Request $request): View
    {
        $staffList = Staff::query()
            ->where('is_active', true)
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

            $businessCurrent = \App\Support\BusinessDate::current();
            $weekStart = $businessCurrent->copy()->startOfWeek(\Illuminate\Support\Carbon::MONDAY);
            $weekEnd = $businessCurrent->copy()->endOfWeek(\Illuminate\Support\Carbon::SUNDAY);

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

        return view('mypage.attendance', [
            'staffList' => $staffList,
            'staff' => $staff,
            'monthStart' => $monthStart,
            'monthAttendances' => $monthAttendances,
            'weekMinutes' => $weekMinutes,
            'monthMinutes' => $monthMinutes,
            'monthLateCount' => $monthLateCount,
        ]);
    }

    public function updateAttendance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'in:out,in'],
            'attendance_id' => ['required', 'integer', 'exists:attendances,id'],
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
            'lunch_in' => ['nullable', 'date_format:H:i'],
            'lunch_out' => ['nullable', 'date_format:H:i'],
            'dinner_in' => ['nullable', 'date_format:H:i'],
            'dinner_out' => ['nullable', 'date_format:H:i'],
            'manager_pin' => ['nullable', 'string', 'digits:4'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return redirect()
                ->route('mypage.attendance')
                ->with('error', 'スタッフが見つかりません。');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id])
                ->with('error', 'PIN が設定されていません。');
        }

        $pinKey = 'pin-attempt:'.$staff->id;

        if (RateLimiter::tooManyAttempts($pinKey, 5)) {
            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id])
                ->with('error', 'PINの入力を複数回間違えました。1分間お待ちください。');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            RateLimiter::hit($pinKey, 60);

            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id])
                ->withInput($request->except(['pin_code', 'manager_pin']))
                ->with('error', '本人の PIN が正しくありません。');
        }

        RateLimiter::clear($pinKey);

        /** @var Attendance|null $attendance */
        $attendance = Attendance::query()->whereKey($validated['attendance_id'])->first();

        if (! $attendance || $attendance->staff_id !== $staff->id) {
            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id])
                ->with('error', '勤怠データが不正です。');
        }

        $date = $attendance->date instanceof Carbon
            ? $attendance->date->copy()->startOfDay()
            : Carbon::parse($attendance->date)->startOfDay();

        if ($validated['mode'] === 'out') {
            $lunchOut = $this->parseShiftOutTime($validated['lunch_out'] ?? null, $date, $attendance->lunch_in_at);
            $dinnerOut = $this->parseShiftOutTime($validated['dinner_out'] ?? null, $date, $attendance->dinner_in_at);

            foreach ([$lunchOut, $dinnerOut] as $parsedTime) {
                if ($parsedTime && $parsedTime->isFuture()) {
                    return redirect()
                        ->back()
                        ->withInput($request->except(['pin_code', 'manager_pin']))
                        ->with('error', '未来の時間は入力できません。');
                }
            }

            $attendance->lunch_out_at = $lunchOut;
            $attendance->dinner_out_at = $dinnerOut;
            $attendance->is_edited_by_admin = false;

            $attendance->save();

            return redirect()
                ->route('mypage.attendance', [
                    'staff_id' => $staff->id,
                    'month' => $date->format('Y-m'),
                ])
                ->with('status', '退勤時間を更新しました。');
        }

        $managerPin = $validated['manager_pin'] ?? null;

        if (blank($managerPin)) {
            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $date->format('Y-m')])
                ->withInput($request->except(['pin_code', 'manager_pin']))
                ->with('error', '出勤時間の変更にはマネージャー PIN が必要です。');
        }

        $manager = Staff::query()
            ->where('is_manager', true)
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->get()
            ->first(fn (Staff $m): bool => hash_equals((string) $m->pin_code, (string) $managerPin));

        if (! $manager) {
            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id, 'month' => $date->format('Y-m')])
                ->withInput($request->except(['pin_code', 'manager_pin']))
                ->with('error', 'マネージャー PIN が正しくないか、権限がありません。');
        }

        $lunchIn = $this->parseShiftInTime($validated['lunch_in'] ?? null, $date);
        $dinnerIn = $this->parseShiftInTime($validated['dinner_in'] ?? null, $date);

        foreach ([$lunchIn, $dinnerIn] as $parsedTime) {
            if ($parsedTime && $parsedTime->isFuture()) {
                return redirect()
                    ->back()
                    ->withInput($request->except(['pin_code', 'manager_pin']))
                    ->with('error', '未来の時間は入力できません。');
            }
        }

        $attendance->lunch_in_at = $lunchIn;
        $attendance->dinner_in_at = $dinnerIn;
        $attendance->approved_by_manager_id = $manager->id;
        $attendance->is_edited_by_admin = false;
        $attendance->save();

        return redirect()
            ->route('mypage.attendance', [
                'staff_id' => $staff->id,
                'month' => $date->format('Y-m'),
            ])
            ->with('status', '出勤時間を更新しました（マネージャー承認済み）。');
    }

    protected function parseShiftInTime(?string $value, Carbon $date): ?Carbon
    {
        return \App\Support\BusinessDate::parseTimeForBusinessDate($value, $date);
    }

    protected function parseShiftOutTime(?string $value, Carbon $date, ?Carbon $inAt): ?Carbon
    {
        return \App\Support\BusinessDate::parseTimeForBusinessDate($value, $date);
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

    private function scheduledStartFromFixedShifts(Staff $staff, string $dayKey, string $mealKey): ?string
    {
        $slot = data_get($staff->fixed_shifts, "{$dayKey}.{$mealKey}");
        if (! is_array($slot) || ! isset($slot[0]) || ! is_string($slot[0])) {
            return null;
        }

        $start = trim($slot[0]);

        return $start !== '' ? $start : null;
    }

}
