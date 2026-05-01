<?php

namespace App\Http\Controllers\Pos2;

use App\Actions\Pos\AddPosOrderFromStaffAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Data\Pos\TableTileAggregate;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionManagementSource;
use App\Exceptions\Pos\SessionManagedByPos2Exception;
use App\Exceptions\RevisionConflictException;
use App\Http\Controllers\Controller;
use App\Models\GuestOrderIdempotency;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\Pos\TableDashboardQueryService;
use App\Services\Pos\TableSessionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * POS V2 用 JSON ゲートウェイ（卓ダッシュボード・セッション注文・Recu）。
 * 旧POSの Action / Service をラップし、Livewire と同一ドメイン状態を返す。
 */
final class Pos2SessionController extends Controller
{
    public function tableDashboard(Request $request): JsonResponse
    {
        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json([
                'shop_id' => $shopId,
                'tiles' => [],
                'generated_at' => now()->toIso8601String(),
                'schema_version' => 1,
            ]);
        }

        $data = app(TableDashboardQueryService::class)->getDashboardData($shopId);
        $tiles = array_map(
            static fn (TableTileAggregate $t): array => $t->toArray(),
            $data->tiles
        );

        return response()->json([
            'shop_id' => $shopId,
            'tiles' => $tiles,
            'generated_at' => now()->toIso8601String(),
            'schema_version' => 1,
        ]);
    }

    public function sessionOrders(Request $request, int $sessionId): JsonResponse
    {
        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json(['message' => 'Shop not configured'], 400);
        }

        $session = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $orders = PosOrder::query()
            ->where('shop_id', $shopId)
            ->where('table_session_id', $sessionId)
            ->where('status', '!=', OrderStatus::Voided)
            ->with(['lines' => static fn ($q) => $q->orderBy('id')])
            ->orderBy('id')
            ->get();

        $orderIds = $orders->pluck('id')->all();
        $guestPosOrderIds = GuestOrderIdempotency::query()
            ->whereIn('pos_order_id', $orderIds)
            ->pluck('pos_order_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $guestSet = array_fill_keys($guestPosOrderIds, true);

        $hasUnackedPlaced = false;
        $mappedOrders = [];

        foreach ($orders as $order) {
            if ($order->status === OrderStatus::Placed) {
                $hasUnackedPlaced = true;
            }

            $orderedBy = isset($guestSet[(int) $order->id]) ? 'guest' : 'staff';
            $linesOut = [];

            foreach ($order->lines as $line) {
                if ($line->status === OrderLineStatus::Cancelled) {
                    continue;
                }

                $snap = is_array($line->snapshot_options_payload) ? $line->snapshot_options_payload : [];
                $styleName = null;
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

                $linesOut[] = [
                    'id' => (int) $line->id,
                    'order_id' => (int) $order->id,
                    'line_status' => $line->status->value,
                    'is_unsent' => $line->status === OrderLineStatus::Placed,
                    'qty' => (int) $line->qty,
                    /** 商品名のみ（右ペイン 1 行目のベース） */
                    'display_name' => (string) $line->snapshot_name,
                    'product_name' => (string) $line->snapshot_name,
                    'style_name' => $styleName,
                    'topping_names' => $toppingNames,
                    'line_total_minor' => (int) $line->line_total_minor,
                    'unit_price_minor' => (int) $line->unit_price_minor,
                    'ordered_by' => $orderedBy,
                ];
            }

            $mappedOrders[] = [
                'id' => (int) $order->id,
                'status' => $order->status->value,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'ordered_by' => $orderedBy,
                'total_minor' => (int) $order->total_price_minor,
                'lines' => $linesOut,
            ];
        }

        return response()->json([
            'table_session_id' => (int) $session->id,
            'restaurant_table_id' => (int) $session->restaurant_table_id,
            'session_revision' => (int) $session->session_revision,
            'has_unacked_placed' => $hasUnackedPlaced,
            'orders' => $mappedOrders,
            'generated_at' => now()->toIso8601String(),
            'schema_version' => 1,
        ]);
    }

    /**
     * ドラフト複数行を同一リクエストで永続化（各行 AddPosOrderFromStaffAction＝旧POSと同じ計算・税）。
     */
    public function submitDraftOrders(Request $request, int $sessionId): JsonResponse
    {
        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json(['message' => 'Shop not configured'], 400);
        }

        $session = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        // 注意: validate() の戻り値には「ルールに書いたキー」しか入らない。
        // lines.*.selected_option_snapshot 等をルールに含めないと JSON から欠落し、スタイル必須で常に 422 になる。
        $request->validate([
            'client_submit_id' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        // JSON ボディから直接 lines を取る（`input('lines')` が検証後にネストを欠くケースへの耐性）。
        /** @var list<array<string, mixed>>|mixed $linesRaw */
        $linesRaw = $request->json('lines');
        if (! is_array($linesRaw)) {
            return response()->json(['message' => 'Invalid lines payload'], 422);
        }

        /** @var list<array<string, mixed>> $lines */
        $lines = $linesRaw;
        $tableId = (int) $session->restaurant_table_id;
        $orderIds = [];

        try {
            DB::transaction(function () use ($shopId, $tableId, $lines, &$orderIds): void {
                foreach ($lines as $lineRow) {
                    $menuItemId = (int) (is_numeric($lineRow['product_id'] ?? null)
                        ? $lineRow['product_id']
                        : preg_replace('/\D/', '', (string) ($lineRow['product_id'] ?? '0')));

                    $qty = (int) ($lineRow['qty'] ?? 1);
                    $styleId = $this->extractStyleIdFromLineRow($lineRow);
                    $toppingIds = [];
                    if (isset($lineRow['topping_snapshots']) && is_array($lineRow['topping_snapshots'])) {
                        foreach ($lineRow['topping_snapshots'] as $snap) {
                            if (is_array($snap) && isset($snap['id'])) {
                                $toppingIds[] = (string) $snap['id'];
                            }
                        }
                    }
                    $note = isset($lineRow['note']) && is_string($lineRow['note']) ? mb_substr($lineRow['note'], 0, 500) : '';

                    $orderIds[] = app(AddPosOrderFromStaffAction::class)->execute(
                        $shopId,
                        $tableId,
                        $menuItemId,
                        $qty,
                        $styleId,
                        $toppingIds,
                        $note,
                        TableSessionManagementSource::Pos2,
                    );
                }
            });
        } catch (SessionManagedByPos2Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'POS2_SESSION_EXCLUSIVE',
            ], 403);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            if (config('app.pos2_debug')) {
                Log::channel('pos2')->warning('pos2.submit_draft.failed', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['message' => __('pos.action_failed')], 500);
        }

        app(TableDashboardQueryService::class)->forgetCachedDashboard($shopId);

        $session->refresh();

        return response()->json([
            'message' => 'OK',
            'order_ids' => $orderIds,
            'session_revision' => (int) $session->session_revision,
            'table_session_id' => (int) $session->id,
        ], 201);
    }

    /**
     * 空卓（セッションなし）からドラフトを送信。
     * {@see TableSessionLifecycleService::getOrCreateActiveSession} でセッションを確定してから
     * submitDraftOrders と同一の処理を実行。
     * 201 レスポンスに table_session_id を含めてフロントが Pinia を更新できるようにする。
     */
    public function submitDraftOrdersForTable(Request $request, int $tableId): JsonResponse
    {
        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json(['message' => 'Shop not configured'], 400);
        }

        $table = RestaurantTable::query()
            ->where('shop_id', $shopId)
            ->whereKey($tableId)
            ->first();

        if ($table === null) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        $request->validate([
            'client_submit_id' => ['nullable', 'string', 'max:64'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:200'],
        ]);

        /** @var list<array<string, mixed>>|mixed $linesRaw */
        $linesRaw = $request->json('lines');
        if (! is_array($linesRaw)) {
            return response()->json(['message' => 'Invalid lines payload'], 422);
        }

        /** @var list<array<string, mixed>> $lines */
        $lines = $linesRaw;
        $orderIds = [];
        $sessionId = null;

        try {
            DB::transaction(function () use ($shopId, $table, $lines, &$orderIds, &$sessionId): void {
                $session = app(TableSessionLifecycleService::class)->getOrCreateActiveSession(
                    $table,
                    TableSessionManagementSource::Pos2,
                );
                $sessionId = (int) $session->id;
                $resolvedTableId = (int) $session->restaurant_table_id;

                foreach ($lines as $lineRow) {
                    $menuItemId = (int) (is_numeric($lineRow['product_id'] ?? null)
                        ? $lineRow['product_id']
                        : preg_replace('/\D/', '', (string) ($lineRow['product_id'] ?? '0')));

                    $qty = (int) ($lineRow['qty'] ?? 1);
                    $styleId = $this->extractStyleIdFromLineRow($lineRow);
                    $toppingIds = [];
                    if (isset($lineRow['topping_snapshots']) && is_array($lineRow['topping_snapshots'])) {
                        foreach ($lineRow['topping_snapshots'] as $snap) {
                            if (is_array($snap) && isset($snap['id'])) {
                                $toppingIds[] = (string) $snap['id'];
                            }
                        }
                    }
                    $note = isset($lineRow['note']) && is_string($lineRow['note']) ? mb_substr($lineRow['note'], 0, 500) : '';

                    $orderIds[] = app(AddPosOrderFromStaffAction::class)->execute(
                        $shopId,
                        $resolvedTableId,
                        $menuItemId,
                        $qty,
                        $styleId,
                        $toppingIds,
                        $note,
                        TableSessionManagementSource::Pos2,
                    );
                }
            });
        } catch (SessionManagedByPos2Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'POS2_SESSION_EXCLUSIVE',
            ], 403);
        } catch (RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        } catch (Throwable $e) {
            if (config('app.pos2_debug')) {
                Log::channel('pos2')->warning('pos2.submit_draft_for_table.failed', [
                    'table_id' => $tableId,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['message' => __('pos.action_failed')], 500);
        }

        app(TableDashboardQueryService::class)->forgetCachedDashboard($shopId);

        $session = TableSession::find($sessionId);

        return response()->json([
            'message' => 'OK',
            'order_ids' => $orderIds,
            'session_revision' => $session ? (int) $session->session_revision : 0,
            'table_session_id' => $sessionId,
        ], 201);
    }

    /**
     * Recu staff（Placed → Confirmed）— 旧POS {@see RecuPlacedOrdersForSessionAction} と同一。
     */
    public function recuStaff(Request $request, int $sessionId): JsonResponse
    {
        $shopId = $this->resolveShopId($request);
        if ($shopId < 1) {
            return response()->json(['message' => 'Shop not configured'], 400);
        }

        $session = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($sessionId)
            ->first();

        if ($session === null) {
            return response()->json(['message' => 'Session not found'], 404);
        }

        $validated = $request->validate([
            'expected_session_revision' => ['required', 'integer', 'min:0'],
        ]);

        $expected = (int) $validated['expected_session_revision'];

        try {
            $n = app(RecuPlacedOrdersForSessionAction::class)->execute(
                $shopId,
                $sessionId,
                $expected,
                TableSessionManagementSource::Pos2,
            );
        } catch (RevisionConflictException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'REVISION_CONFLICT',
                'context' => [
                    'resource' => $e->resource,
                    'id' => $e->id,
                    'current_revision' => $e->currentRevision,
                    'client_sent_revision' => $e->clientSentRevision,
                ],
            ], 409);
        } catch (Throwable $e) {
            if (config('app.pos2_debug')) {
                Log::channel('pos2')->warning('pos2.recu_staff.failed', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json(['message' => __('pos.action_failed')], 500);
        }

        app(TableDashboardQueryService::class)->forgetCachedDashboard($shopId);
        $session->refresh();

        return response()->json([
            'message' => 'OK',
            'confirmed_batches' => $n,
            'session_revision' => (int) $session->session_revision,
            'table_session_id' => (int) $session->id,
        ]);
    }

    /**
     * ドラフト行からスタイル ID を取り出す（snake / camel 両対応。値 null の isset 罠を避ける）。
     *
     * @param  array<string, mixed>  $lineRow
     */
    private function extractStyleIdFromLineRow(array $lineRow): ?string
    {
        $snap = $lineRow['selected_option_snapshot'] ?? $lineRow['selectedOptionSnapshot'] ?? null;
        if (! is_array($snap)) {
            return null;
        }
        $raw = $snap['id'] ?? null;
        if ($raw === null) {
            return null;
        }
        $t = trim((string) $raw);

        return $t === '' ? null : $t;
    }

    private function resolveShopId(Request $request): int
    {
        $candidate = (int) ($request->session()->get('pos2.active_shop_id')
            ?? $request->session()->get('kds.active_shop_id')
            ?? env('POS_DEFAULT_SHOP_ID', 0));

        return max(0, $candidate);
    }
}
