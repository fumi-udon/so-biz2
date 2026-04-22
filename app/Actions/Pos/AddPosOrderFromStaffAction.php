<?php

namespace App\Actions\Pos;

use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Pricing\PricingInput;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Events\Pos\PosOrderPlaced;
use App\Exceptions\GuestOrderValidationException;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\Pos\PosLineComputationService;
use App\Services\Pos\TableSessionLifecycleService;
use App\Support\Pos\Receipt\ReceiptTaxMath;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * テーブルダッシュボードから 1 バッチ（1 行）の卓注文を追加（価格はメニューマスタ基準のゼロトラスト計算）。
 */
final class AddPosOrderFromStaffAction
{
    /**
     * @param  list<string>  $toppingIds
     */
    public function execute(
        int $shopId,
        int $restaurantTableId,
        int $menuItemId,
        int $qty,
        ?string $styleId,
        array $toppingIds,
        string $note = '',
    ): int {
        if ($shopId < 1 || $restaurantTableId < 1 || $menuItemId < 1) {
            throw new RuntimeException(__('pos.add_invalid_input'));
        }

        $qty = max(1, min(200, (int) $qty));
        $cleanToppings = array_values(array_filter(
            array_map(static fn ($id): string => trim((string) $id), $toppingIds),
            static fn (string $id): bool => $id !== ''
        ));
        $toppingSnapshots = array_map(
            static fn (string $id): array => ['id' => $id],
            $cleanToppings
        );

        try {
            return (int) DB::transaction(function () use (
                $shopId,
                $restaurantTableId,
                $menuItemId,
                $qty,
                $styleId,
                $toppingSnapshots,
                $note
            ): int {
                $table = RestaurantTable::query()
                    ->where('shop_id', $shopId)
                    ->whereKey($restaurantTableId)
                    ->lockForUpdate()
                    ->first();

                if ($table === null) {
                    throw new RuntimeException(__('pos.table_not_found'));
                }

                $session = app(TableSessionLifecycleService::class)->getOrCreateActiveSession($table);
                $session = TableSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

                $item = MenuItem::query()
                    ->where('shop_id', $shopId)
                    ->whereKey($menuItemId)
                    ->lockForUpdate()
                    ->first();

                if ($item === null) {
                    throw new RuntimeException(__('pos.menu_item_not_found'));
                }

                if (! $item->is_active) {
                    throw new RuntimeException(__('pos.menu_item_inactive'));
                }

                $row = [
                    'styleId' => $styleId,
                    'toppingSnapshots' => $toppingSnapshots,
                    'note' => $note,
                ];

                $lineCalc = app(PosLineComputationService::class);
                $unit = $lineCalc->computeUnitPriceMinor($item, $row);
                $lineTotal = $unit * $qty;
                $pricing = app(PricingEngine::class)->calculate(new PricingInput(
                    lineSubtotalsMinor: [$lineTotal],
                    lineDiscountsMinor: [0],
                    orderDiscountMinor: 0,
                ));
                $snap = $lineCalc->buildSnapshotOptionsPayload($item, $row);

                $name = (string) $item->name;
                $k = $item->kitchen_name;
                $kSnap = (is_string($k) && trim($k) !== '') ? (string) $k : $name;

                $order = PosOrder::query()->create([
                    'shop_id' => $shopId,
                    'table_session_id' => $session->id,
                    'status' => OrderStatus::Placed,
                    'total_price_minor' => $pricing->finalTotalMinor,
                    'rounding_adjustment_minor' => $pricing->roundingAdjustmentMinor,
                    'placed_at' => now(),
                ]);

                OrderLine::query()->create([
                    'order_id' => $order->id,
                    'menu_item_id' => $item->id,
                    'qty' => $qty,
                    'unit_price_minor' => $unit,
                    'line_total_minor' => $lineTotal,
                    'vat_rate_percent' => ReceiptTaxMath::defaultVatPercent(),
                    'snapshot_name' => $name,
                    'snapshot_kitchen_name' => $kSnap,
                    'snapshot_options_payload' => $snap,
                    'status' => OrderLineStatus::Placed,
                ]);

                $eventShopId = (int) $shopId;
                $eventTableId = (int) $table->id;
                $eventSessionId = (int) $session->id;
                $eventOrderId = (int) $order->id;
                $session->increment('session_revision');

                DB::afterCommit(function () use ($eventShopId, $eventTableId, $eventSessionId, $eventOrderId): void {
                    PosOrderPlaced::dispatch($eventShopId, $eventTableId, $eventSessionId, $eventOrderId);
                });

                return (int) $order->id;
            });
        } catch (GuestOrderValidationException $e) {
            throw new RuntimeException($e->getMessage());
        }
    }
}
