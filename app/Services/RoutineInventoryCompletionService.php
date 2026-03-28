<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\RoutineTask;
use App\Models\RoutineTaskLog;
use App\Models\Staff;
use App\Support\BusinessDate;
use Illuminate\Support\Collection;

class RoutineInventoryCompletionService
{
    /**
     * 指定スタッフについて、営業日に未完了のルーティンタスク一覧（モデルコレクション）。
     *
     * @return Collection<int, RoutineTask>
     */
    public function incompleteRoutineTasksForStaff(Staff $staff, ?string $dateString = null): Collection
    {
        $dateString ??= BusinessDate::toDateString();

        $tasks = RoutineTask::query()
            ->where('assigned_staff_id', $staff->id)
            ->where('shop_id', $staff->shop_id)
            ->where('is_active', true)
            ->get();

        return $tasks->filter(function (RoutineTask $task) use ($dateString): bool {
            return ! RoutineTaskLog::query()
                ->where('routine_task_id', $task->id)
                ->whereDate('date', $dateString)
                ->exists();
        })->values();
    }

    /**
     * 指定スタッフについて、営業日に未入力の棚卸しアイテム一覧。
     *
     * @return Collection<int, InventoryItem>
     */
    public function incompleteInventoryItemsForStaff(Staff $staff, ?string $dateString = null): Collection
    {
        $dateString ??= BusinessDate::toDateString();

        $items = InventoryItem::query()
            ->where('assigned_staff_id', $staff->id)
            ->where('shop_id', $staff->shop_id)
            ->where('is_active', true)
            ->get();

        return $items->filter(function (InventoryItem $item) use ($dateString): bool {
            return ! InventoryRecord::query()
                ->where('inventory_item_id', $item->id)
                ->whereDate('date', $dateString)
                ->exists();
        })->values();
    }

    public function staffHasAllRoutineAndInventoryDone(Staff $staff, ?string $dateString = null): bool
    {
        return $this->incompleteRoutineTasksForStaff($staff, $dateString)->isEmpty()
            && $this->incompleteInventoryItemsForStaff($staff, $dateString)->isEmpty();
    }

    /**
     * 全店・全スタッフの未完了を人間可読な行の配列で返す（クローズチェック用）。
     *
     * @return list<string>
     */
    public function globalIncompleteSummaries(?string $dateString = null): array
    {
        $dateString ??= BusinessDate::toDateString();
        $lines = [];

        $staffIds = Staff::query()->where('is_active', true)->pluck('id');

        foreach ($staffIds as $sid) {
            $staff = Staff::query()->find($sid);
            if (! $staff) {
                continue;
            }

            foreach ($this->incompleteRoutineTasksForStaff($staff, $dateString) as $task) {
                $lines[] = $staff->name.'（'.$task->category.'：'.$task->name.'）';
            }

            foreach ($this->incompleteInventoryItemsForStaff($staff, $dateString) as $item) {
                $lines[] = $staff->name.'（棚卸し：'.$item->category.' '.$item->name.'）';
            }
        }

        return $lines;
    }
}
