<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\RoutineTask;
use App\Models\RoutineTaskLog;
use App\Models\Staff;
use App\Support\BusinessDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MyPageController extends Controller
{
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
        $inventoryValues = [];

        if ($staff) {
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

            foreach ($inventoryItems as $item) {
                $inventoryValues[$item->id] = $records->get($item->id)?->value;
            }
        }

        return view('mypage.index', [
            'staffList' => $staffList,
            'staff' => $staff,
            'dateString' => $dateString,
            'routineTasks' => $routineTasks,
            'routineLogIds' => $routineLogIds,
            'inventoryItems' => $inventoryItems,
            'inventoryValues' => $inventoryValues,
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

        $itemsById = InventoryItem::query()
            ->where('assigned_staff_id', $staff->id)
            ->where('shop_id', $staff->shop_id)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

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

        DB::transaction(function () use ($staff, $dateString, $now, $routineInput, $invInput, $itemsById): void {
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
        });

        return redirect()
            ->route('mypage.index', ['staff_id' => $staff->id])
            ->with('status', '保存しました。');
    }
}
