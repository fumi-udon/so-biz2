<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\RoutineTask;
use App\Models\RoutineTaskLog;
use App\Models\Staff;
use App\Support\BusinessDate;
use App\Support\InventorySettingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MyPageController extends Controller
{
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

    public function index(Request $request): View
    {
        $staffList = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $staffId = $request->integer('staff_id') ?: null;
        $staff = $staffId ? Staff::query()->where('id', $staffId)->where('is_active', true)->first() : null;

        $dateString = BusinessDate::toDateString();

        $routineTasks = collect();
        $inventoryItems = collect();
        $routineLogIds = collect();
        $inventoryTimingRows = [];
        $motivationLevel = 1;
        $todayClockInLabel = null;

        if ($staff) {
            $completionCount = RoutineTaskLog::query()
                ->where('completed_by_staff_id', $staff->id)
                ->count();
            $motivationLevel = max(1, intdiv($completionCount, 10) + 1);

            $attendance = Attendance::query()
                ->where('staff_id', $staff->id)
                ->whereDate('date', $dateString)
                ->first();

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
        ]);
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
            : now()->format('Y-m-01'))->startOfMonth();

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

            $weekStart = Carbon::now()->startOfWeek(Carbon::MONDAY);
            $weekEnd = Carbon::now()->copy()->endOfWeek(Carbon::SUNDAY);

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

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            return redirect()
                ->route('mypage.attendance', ['staff_id' => $staff->id])
                ->withInput($request->except(['pin_code', 'manager_pin']))
                ->with('error', '本人の PIN が正しくありません。');
        }

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
            $attendance->lunch_out_at = $this->parseNullableTime($validated['lunch_out'] ?? null, $date);
            $attendance->dinner_out_at = $this->parseNullableTime($validated['dinner_out'] ?? null, $date);
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

        $attendance->lunch_in_at = $this->parseNullableTime($validated['lunch_in'] ?? null, $date);
        $attendance->dinner_in_at = $this->parseNullableTime($validated['dinner_in'] ?? null, $date);
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

    protected function parseNullableTime(?string $value, Carbon $date): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $day = $date->copy()->startOfDay();

        return $day->setTimeFromTimeString($value);
    }
}
