<?php

namespace App\Http\Controllers\Kds;

use App\Actions\Kds\UpdateOrderLineStatusAction;
use App\Enums\OrderLineStatus;
use App\Exceptions\RevisionConflictException;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\OrderLine;
use App\Services\Kds\KdsQueryService;
use App\Support\KdsFilterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

final class KdsV2Controller extends Controller
{
    public function index(Request $request): View
    {
        return view('kds2.app');
    }

    public function tickets(Request $request): JsonResponse
    {
        $shopId = (int) $request->session()->get('kds.active_shop_id', 0);
        $lines = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard($shopId);

        // バッチキー: COALESCE(NULLIF(TRIM(kds_ticket_batch_id), ''), 'o:' + order_id)
        $byBatch = [];
        foreach ($lines as $line) {
            $rawBatch = trim((string) ($line->kds_ticket_batch_id ?? ''));
            $batchKey = $rawBatch !== '' ? 'b:'.$rawBatch : 'o:'.$line->order_id;
            $byBatch[$batchKey][] = $line;
        }

        $batches = [];
        foreach ($byBatch as $batchKey => $batchLines) {
            $pendingCount = collect($batchLines)
                ->filter(fn (OrderLine $l) => in_array($l->status, [OrderLineStatus::Confirmed, OrderLineStatus::Cooking], true))
                ->count();

            $tableName = $batchLines[0]->order?->tableSession?->restaurantTable?->name ?? '?';

            $tickets = [];
            foreach ($batchLines as $line) {
                $isPending = in_array($line->status, [OrderLineStatus::Confirmed, OrderLineStatus::Cooking], true);
                $parts = $this->buildKds2TicketDisplayParts($line);

                $tickets[] = [
                    'id' => $line->id,
                    'rev' => $line->line_revision,
                    'name' => $parts['name'],
                    'options' => $parts['options'],
                    'qty' => $line->qty,
                    'status' => $line->status->value,
                    'cat_id' => $line->menuItem?->menu_category_id,
                    'is_last' => $isPending && $pendingCount === 1,
                ];
            }

            $batches[] = [
                'key' => $batchKey,
                'table' => $tableName,
                'tickets' => $tickets,
            ];
        }

        return response()->json([
            'shop_id' => $shopId,
            'batches' => $batches,
            'queued' => 0, // pending_actions カウントはクライアント側 Pinia で管理
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @throws Throwable
     */
    public function markServed(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'rev' => ['required', 'integer', 'min:0'],
        ]);

        /** @var OrderLine|null $line */
        $line = OrderLine::query()->find($id);

        if ($line === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        // 冪等: 既に Served なら 200 を返す
        if ($line->status === OrderLineStatus::Served) {
            return response()->json([
                'id' => $line->id,
                'rev' => $line->line_revision,
                'status' => 'served',
            ]);
        }

        try {
            $updated = app(UpdateOrderLineStatusAction::class)
                ->execute($id, OrderLineStatus::Served->value, (int) $validated['rev']);

            return response()->json([
                'id' => $updated->id,
                'rev' => $updated->line_revision,
                'status' => 'served',
            ]);
        } catch (RevisionConflictException $e) {
            return response()->json([
                'error' => 'conflict',
                'current_rev' => $e->currentRevision,
            ], 409);
        }
    }

    public function master(Request $request): JsonResponse
    {
        $shopId = (int) $request->session()->get('kds.active_shop_id', 0);

        /** @var array<string, mixed> $data */
        $data = Cache::remember('kds2_master_'.$shopId, 300, function () use ($shopId): array {
            $categories = MenuCategory::query()
                ->where('shop_id', $shopId)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'slug', 'sort_order'])
                ->toArray();

            return [
                'categories' => $categories,
                'kitchen_category_ids' => KdsFilterSetting::kitchenCategoryIds($shopId),
                'hall_category_ids' => KdsFilterSetting::hallCategoryIds($shopId),
                'filter_strict' => KdsFilterSetting::isCategoryFilterConfigured($shopId),
            ];
        });

        return response()->json($data);
    }

    /**
     * KDS2 表示用: 1行目 = snapshot_kitchen_name + 半角スペース + スタイル名、2行目 = トッピング名（カンマ区切り）。
     *
     * @return array{name: string, options: string}
     */
    private function buildKds2TicketDisplayParts(OrderLine $line): array
    {
        $snap = is_array($line->snapshot_options_payload) ? $line->snapshot_options_payload : [];

        $kitchen = trim((string) ($line->snapshot_kitchen_name ?? ''));
        if ($kitchen === '') {
            $kitchen = trim((string) ($line->snapshot_name ?? ''));
        }
        if ($kitchen === '') {
            $kitchen = trim((string) ($line->menuItem?->kitchen_name ?? $line->menuItem?->name ?? ''));
        }

        $styleName = '';
        if (isset($snap['style']) && is_array($snap['style'])) {
            $sn = trim((string) ($snap['style']['name'] ?? ''));
            if ($sn !== '') {
                $styleName = $sn;
            }
        }

        $toppingNames = [];
        if (isset($snap['toppings']) && is_array($snap['toppings'])) {
            foreach ($snap['toppings'] as $t) {
                if (! is_array($t)) {
                    continue;
                }
                $tn = trim((string) ($t['name'] ?? ''));
                if ($tn !== '') {
                    $toppingNames[] = $tn;
                }
            }
        }

        $name = $kitchen;
        if ($styleName !== '') {
            $name = $kitchen !== '' ? $kitchen.' '.$styleName : $styleName;
        }

        $options = $toppingNames === [] ? '' : implode(', ', $toppingNames);

        return [
            'name' => $name,
            'options' => $options,
        ];
    }
}
