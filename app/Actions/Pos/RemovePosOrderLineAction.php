<?php

namespace App\Actions\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RemovePosOrderLineAction
{
    public function execute(int $shopId, int $orderLineId): void
    {
        DB::transaction(function () use ($shopId, $orderLineId): void {
            $line = OrderLine::query()
                ->whereKey($orderLineId)
                ->lockForUpdate()
                ->with(['order' => static fn ($q) => $q->select(['id', 'shop_id', 'table_session_id', 'status', 'total_price_minor'])])
                ->first();

            if ($line === null) {
                throw new RuntimeException(__('pos.line_not_found'));
            }

            $order = $line->order;
            if ((int) $order->shop_id !== $shopId) {
                throw new RuntimeException(__('pos.line_forbidden'));
            }

            if ($order->status === OrderStatus::Voided) {
                throw new RuntimeException(__('pos.order_voided_cannot_edit'));
            }

            $session = TableSession::query()
                ->whereKey((int) $order->table_session_id)
                ->where('shop_id', $shopId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new RuntimeException(__('pos.line_forbidden'));
            }

            // Intentionally bypass Eloquent model events here.
            // POS deletion flow can be authorized in UI (PIN/level) even for
            // confirmed orders, while OrderLineObserver blocks model-level delete
            // for non-draft/non-placed states.
            DB::table('order_lines')
                ->where('id', (int) $line->id)
                ->delete();

            $remaining = OrderLine::query()
                ->where('order_id', (int) $order->id)
                ->get();

            if ($remaining->isEmpty()) {
                PosOrder::query()
                    ->whereKey((int) $order->id)
                    ->update([
                        'status' => OrderStatus::Voided,
                        'total_price_minor' => 0,
                    ]);
            } else {
                $sum = (int) $remaining->sum('line_total_minor');
                PosOrder::query()
                    ->whereKey((int) $order->id)
                    ->update(['total_price_minor' => $sum]);
            }

            $remainingActiveOrderCount = PosOrder::query()
                ->where('table_session_id', (int) $session->id)
                ->where('status', '!=', OrderStatus::Voided)
                ->count();

            if ($remainingActiveOrderCount === 0) {
                $session->status = TableSessionStatus::Closed;
                $session->closed_at = now();
                $session->session_revision = (int) $session->session_revision + 1;
                $session->save();
            } else {
                // Printed/Billed becomes stale immediately after any line deletion.
                $session->last_addition_printed_at = null;
                $session->session_revision = (int) $session->session_revision + 1;
                $session->save();
            }
        });
    }
}
