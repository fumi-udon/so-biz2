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
        $inventoryQuantities = [];

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
                $inventoryQuantities[$item->id] = $records->get($item->id)?->quantity;
            }
        }

        return view('mypage.index', [
            'staffList' => $staffList,
            'staff' => $staff,
            'dateString' => $dateString,
            'routineTasks' => $routineTasks,
            'routineLogIds' => $routineLogIds,
            'inventoryItems' => $inventoryItems,
            'inventoryQuantities' => $inventoryQuantities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'pin_code' => ['required', 'string', 'digits:4'],
            'routine_task' => ['nullable', 'array'],
            'inventory_qty' => ['nullable', 'array'],
            'inventory_qty.*' => ['nullable', 'numeric', 'min:0'],
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
        $qtyInput = $validated['inventory_qty'] ?? [];

        DB::transaction(function () use ($staff, $dateString, $now, $routineInput, $qtyInput): void {
            $allowedRoutineIds = RoutineTask::query()
                ->where('assigned_staff_id', $staff->id)
                ->where('shop_id', $staff->shop_id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            foreach ($allowedRoutineIds as $rid) {
                $checked = ! empty($routineInput[$rid]);
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

            $allowedItemIds = InventoryItem::query()
                ->where('assigned_staff_id', $staff->id)
                ->where('shop_id', $staff->shop_id)
                ->where('is_active', true)
                ->pluck('id')
                ->all();

            foreach ($allowedItemIds as $iid) {
                if (! array_key_exists($iid, $qtyInput)) {
                    continue;
                }

                $raw = $qtyInput[$iid];
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
                        'quantity' => $raw,
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
