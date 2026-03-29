<?php

namespace App\Http\Controllers;

use App\Models\InventoryItem;
use App\Models\InventoryRecord;
use App\Models\Staff;
use App\Support\BusinessDate;
use App\Support\InventorySettingOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientInventoryController extends Controller
{
    public function index(): View
    {
        $dateString = BusinessDate::toDateString();

        $items = InventoryItem::query()
            ->where('is_active', true)
            ->with(['assignedStaff:id,name'])
            ->orderBy('shop_id')
            ->orderBy('timing')
            ->orderBy('assigned_staff_id')
            ->get();

        $records = InventoryRecord::query()
            ->whereIn('inventory_item_id', $items->pluck('id'))
            ->whereDate('date', $dateString)
            ->get()
            ->keyBy('inventory_item_id');

        $byTiming = $items->groupBy(fn (InventoryItem $item): string => (string) ($item->timing ?? ''));

        $orderedTimings = $this->orderedTimingsForKeys($byTiming->keys());

        $timingSections = [];
        foreach ($orderedTimings as $timingKey) {
            $timingItems = $byTiming->get($timingKey, collect());
            if ($timingItems->isEmpty()) {
                continue;
            }

            $byStaff = $timingItems->groupBy('assigned_staff_id');
            $rows = [];

            foreach ($byStaff as $staffId => $groupItems) {
                $staffId = (int) $staffId;
                $first = $groupItems->first();
                $staffName = $first?->assignedStaff?->name ?? ('#'.$staffId);

                $total = $groupItems->count();
                $filled = 0;
                foreach ($groupItems as $item) {
                    $val = $records->get($item->id)?->value;
                    if ($val !== null && $val !== '') {
                        $filled++;
                    }
                }

                $rows[] = [
                    'staff_id' => $staffId,
                    'staff_name' => $staffName,
                    'complete' => $total > 0 && $filled >= $total,
                    'total' => $total,
                    'filled' => $filled,
                ];
            }

            usort($rows, static fn (array $a, array $b): int => strcmp($a['staff_name'], $b['staff_name']));

            $timingSections[] = [
                'timing' => $timingKey,
                'rows' => $rows,
            ];
        }

        return view('inventory.index', [
            'dateString' => $dateString,
            'timingSections' => $timingSections,
        ]);
    }

    /**
     * @param  Collection<int|string, mixed>  $timingKeys
     * @return list<string>
     */
    protected function orderedTimingsForKeys(Collection $timingKeys): array
    {
        $master = array_keys(InventorySettingOptions::timingForSelect());
        $present = $timingKeys->filter(static fn ($k): bool => $k !== null)->unique()->values()->all();
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

    public function input(string $timing, int $staff_id): View|RedirectResponse
    {
        $staff = Staff::query()->where('id', $staff_id)->where('is_active', true)->first();
        if (! $staff) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'スタッフが見つかりません。');
        }

        $dateString = BusinessDate::toDateString();

        $inventoryItems = InventoryItem::query()
            ->where('assigned_staff_id', $staff->id)
            ->where('shop_id', $staff->shop_id)
            ->where('is_active', true)
            ->where('timing', $timing)
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        if ($inventoryItems->isEmpty()) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'このタイミングの棚卸し品目はありません。');
        }

        $records = InventoryRecord::query()
            ->whereIn('inventory_item_id', $inventoryItems->pluck('id'))
            ->whereDate('date', $dateString)
            ->get()
            ->keyBy('inventory_item_id');

        $inventoryValues = [];
        foreach ($inventoryItems as $item) {
            $inventoryValues[$item->id] = $records->get($item->id)?->value;
        }

        return view('inventory.input', [
            'staff' => $staff,
            'timing' => $timing,
            'dateString' => $dateString,
            'inventoryItems' => $inventoryItems,
            'inventoryValues' => $inventoryValues,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (count($request->all()) >= (int) (ini_get('max_input_vars') ?: 1000) - 20) {
            abort(400, 'データ量がサーバーの上限を超過しました。システム管理者に連絡してください。');
        }

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'timing' => ['required', 'string', 'max:255'],
            'pin_code' => ['required', 'string', 'digits:4'],
            'inventory_val' => ['nullable', 'array'],
            'inventory_val.*' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff = Staff::query()
            ->where('id', $validated['staff_id'])
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'スタッフが見つかりません。');
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            return redirect()
                ->route('inventory.input', ['timing' => $validated['timing'], 'staff_id' => $staff->id])
                ->with('error', 'PIN が設定されていません。');
        }

        if (! hash_equals((string) $staff->pin_code, (string) $validated['pin_code'])) {
            return redirect()
                ->route('inventory.input', ['timing' => $validated['timing'], 'staff_id' => $staff->id])
                ->withInput($request->except('pin_code'))
                ->with('error', 'PIN が正しくありません。');
        }

        $timing = $validated['timing'];
        $dateString = BusinessDate::toDateString();

        $invInput = $validated['inventory_val'] ?? [];
        if (! is_array($invInput)) {
            $invInput = [];
        }

        $itemsById = InventoryItem::query()
            ->where('assigned_staff_id', $staff->id)
            ->where('shop_id', $staff->shop_id)
            ->where('is_active', true)
            ->where('timing', $timing)
            ->get()
            ->keyBy('id');

        if ($itemsById->isEmpty()) {
            return redirect()
                ->route('inventory.index')
                ->with('error', 'このタイミングの棚卸し品目はありません。');
        }

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

        DB::transaction(function () use ($staff, $dateString, $invInput, $itemsById): void {
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
            ->route('inventory.index')
            ->with('status', '保存しました。');
    }
}
