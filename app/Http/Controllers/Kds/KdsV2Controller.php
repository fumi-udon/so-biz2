<?php

namespace App\Http\Controllers\Kds;

use App\Actions\Kds\UpdateOrderLineStatusAction;
use App\Enums\OrderLineStatus;
use App\Exceptions\RevisionConflictException;
use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\OrderLine;
use App\Services\Kds\KdsQueryService;
use App\Support\KdsDictionarySetting;
use App\Support\KdsFilterSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Throwable;

final class KdsV2Controller extends Controller
{
    private const KDS2_TICKET_MISSING_SORT = 999_999_999;

    public function index(Request $request): View
    {
        return view('kds2.app');
    }

    public function tickets(Request $request): JsonResponse
    {
        $shopId = (int) $request->session()->get('kds.active_shop_id', 0);
        $lines = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard($shopId);
        $dict = $this->getDictionaryMap($shopId);

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

            $tableSession = $batchLines[0]->order?->tableSession;
            $tableName = $tableSession?->restaurantTable?->name ?? '?';
            $customerName = trim((string) ($tableSession?->customer_name ?? ''));
            if ($customerName !== '') {
                $tableName = $tableName.' / '.$customerName;
            }

            $tickets = [];
            foreach ($batchLines as $line) {
                $isPending = in_array($line->status, [OrderLineStatus::Confirmed, OrderLineStatus::Cooking], true);
                $parts = $this->buildKds2TicketDisplayParts($line, $dict);
                $sortMeta = $this->kds2TicketSortMeta($line);

                $tickets[] = [
                    'id' => $line->id,
                    'rev' => $line->line_revision,
                    'name' => $parts['name'],
                    'options' => $parts['options'],
                    'qty' => $line->qty,
                    'status' => $line->status->value,
                    'cat_id' => $line->menuItem?->menu_category_id,
                    'is_last' => $isPending && $pendingCount === 1,
                    'category_sort' => $sortMeta['category_sort'],
                    'item_sort' => $sortMeta['item_sort'],
                    'sort_name' => $sortMeta['sort_name'],
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

    public function dictionary(Request $request): JsonResponse
    {
        $shopId = (int) $request->session()->get('kds.active_shop_id', 0);

        return response()->json($this->getDictionaryMap($shopId));
    }

    /**
     * @return array<string, string>
     */
    private function getDictionaryMap(int $shopId): array
    {
        if ($shopId < 1) {
            return [];
        }

        $cacheKey = KdsDictionarySetting::jsonCacheKey($shopId);

        /** @var array<string, string> */
        return Cache::rememberForever($cacheKey, function () use ($shopId): array {
            $text = KdsDictionarySetting::getText($shopId);
            $parsed = [];
            foreach (explode("\n", $text) as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $matchKey = KdsDictionarySetting::normalizeMatchKey($k);
                    if ($matchKey !== '') {
                        $parsed[$matchKey] = trim($v);
                    }
                }
            }

            return $parsed;
        });
    }

    /**
     * KDS2 表示用: 1行目 = snapshot_kitchen_name + 半角スペース + スタイル名（辞書変換済み）、2行目 = トッピング名（カンマ区切り）。
     *
     * @param  array<string, string>  $dict
     * @return array{name: string, options: string}
     */
    private function buildKds2TicketDisplayParts(OrderLine $line, array $dict = []): array
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
                $matchKey = KdsDictionarySetting::normalizeMatchKey($sn);
                $styleName = $dict[$matchKey] ?? $sn;
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

    /**
     * @return array{category_sort: int, item_sort: int, sort_name: string}
     */
    private function kds2TicketSortMeta(OrderLine $line): array
    {
        $missing = self::KDS2_TICKET_MISSING_SORT;
        $rawName = (string) ($line->snapshot_kitchen_name ?: $line->snapshot_name ?: $line->menuItem?->name ?: '');
        $sortName = mb_strtolower(trim($rawName));

        return [
            'category_sort' => (int) ($line->menuItem?->menuCategory?->sort_order ?? $missing),
            'item_sort' => (int) ($line->menuItem?->sort_order ?? $missing),
            'sort_name' => $sortName,
        ];
    }
}
