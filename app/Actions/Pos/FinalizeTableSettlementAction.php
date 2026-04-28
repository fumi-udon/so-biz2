<?php

namespace App\Actions\Pos;

use App\Domains\Pos\Pricing\PricingEngine;
use App\Domains\Pos\Pricing\PricingResult;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableSessionStatus;
use App\Exceptions\Pos\InsufficientTenderException;
use App\Exceptions\Pos\PendingOrdersRemainException;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Exceptions\RevisionConflictException;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\TableSessionSettlement;
use App\Services\Pos\TableSessionLifecycleService;
use App\Support\Pos\StaffTableSettlementPricing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Phase 3 heart: transactionally settle a table session.
 *
 * Invariants guaranteed by this Action (all enforced inside a single DB
 * transaction with pessimistic row locks):
 *
 *   I1. At most one settlement row per table_session (DB unique + app check).
 *   I2. Client-sent session_revision must match the DB's current revision at
 *       the instant of settlement (optimistic-lock via revision + FOR UPDATE).
 *   I3. No unacknowledged (OrderStatus::Placed) orders remain for the session.
 *   I4. The PricingEngine is invoked once with *all* non-voided order lines
 *       and the summed order-level discount to obtain the canonical
 *       `rounding_adjustment_minor` and `final_total_minor`.
 *   I5. SUM(orders.rounding_adjustment_minor WHERE session=S AND status<>Voided)
 *       == settlements.rounding_adjustment_minor.
 *       Achieved by zeroing all non-voided orders' rounding and assigning
 *       the full session-level rounding to the latest non-voided order.
 *   I6. For cash/voucher payments: tendered_minor >= final_total_minor.
 *       Card uses final_total_minor verbatim (tendered_minor is ignored).
 *       BypassForced allows any tendered amount (manager responsibility).
 *   I7. After success: session.status = Closed, session.closed_at = now(),
 *       session.session_revision += 1, all non-voided orders marked Paid.
 */
final class FinalizeTableSettlementAction
{
    public function __construct(
        private readonly PricingEngine $pricingEngine,
        private readonly TableSessionLifecycleService $tableSessionLifecycleService,
    ) {}

    public function execute(FinalizeTableSettlementRequest $req): TableSessionSettlement
    {
        // TEMP: POS_SETTLE_DEBUG
        $traceId = $req->debugTraceId ?: sprintf('settle-action-%d-%d', $req->shopId, $req->tableSessionId);
        $this->debugSettleLog('action_enter', [
            'trace_id' => $traceId,
            'shop_id' => $req->shopId,
            'table_session_id' => $req->tableSessionId,
            'expected_revision' => $req->expectedSessionRevision,
            'actor_user_id' => $req->actorUserId,
        ]);

        return DB::transaction(function () use ($req, $traceId): TableSessionSettlement {
            // I1 idempotent path: if settlement already exists, recover any
            // drifted session/order state and return the canonical snapshot.
            $existingSettlement = TableSessionSettlement::query()
                ->where('table_session_id', $req->tableSessionId)
                ->lockForUpdate()
                ->first();
            if ($existingSettlement !== null) {
                $this->debugSettleLog('action_already_settled_detected', [
                    'trace_id' => $traceId,
                    'table_session_id' => $req->tableSessionId,
                    'settlement_id' => (int) $existingSettlement->id,
                ]);

                return $this->repairSettledSessionState($req, $existingSettlement, $traceId);
            }

            // Lock the physical table first (mirrors AddPosOrderFromStaffAction
            // and CheckoutTableSessionAction) to prevent cross-flow deadlocks
            // with the guest-submit path.
            $preSession = TableSession::query()
                ->whereKey($req->tableSessionId)
                ->where('shop_id', $req->shopId)
                ->first();

            if ($preSession === null) {
                throw new RuntimeException(__('rad_table.active_session_not_found'));
            }

            RestaurantTable::query()
                ->whereKey($preSession->restaurant_table_id)
                ->where('shop_id', $req->shopId)
                ->lockForUpdate()
                ->first();

            $session = TableSession::query()
                ->whereKey($req->tableSessionId)
                ->where('shop_id', $req->shopId)
                ->where('status', TableSessionStatus::Active)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new SessionAlreadySettledException($req->tableSessionId);
            }
            $this->debugSettleLog('action_session_locked', [
                'trace_id' => $traceId,
                'table_session_id' => (int) $session->id,
                'actual_revision' => (int) $session->session_revision,
                'expected_revision' => $req->expectedSessionRevision,
            ]);

            if ((int) $session->session_revision !== $req->expectedSessionRevision) {
                $this->debugSettleLog('action_revision_conflict', [
                    'trace_id' => $traceId,
                    'table_session_id' => (int) $session->id,
                    'actual_revision' => (int) $session->session_revision,
                    'expected_revision' => $req->expectedSessionRevision,
                ]);
                throw new RevisionConflictException(
                    resource: 'table_session',
                    id: (int) $session->id,
                    currentRevision: (int) $session->session_revision,
                    clientSentRevision: $req->expectedSessionRevision,
                );
            }

            $pendingCount = (int) $session->posOrders()
                ->where('status', OrderStatus::Placed)
                ->count();
            $this->debugSettleLog('action_pending_count', [
                'trace_id' => $traceId,
                'table_session_id' => (int) $session->id,
                'pending_count' => $pendingCount,
            ]);

            if ($pendingCount > 0) {
                throw new PendingOrdersRemainException(
                    tableSessionId: (int) $session->id,
                    pendingCount: $pendingCount,
                );
            }

            /** @var Collection<int, PosOrder> $orders */
            $orders = $session->posOrders()
                ->where('status', '!=', OrderStatus::Voided)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $pricing = StaffTableSettlementPricing::calculateFromPosOrders(
                $orders,
                (int) $session->restaurant_table_id,
                $this->pricingEngine,
            );

            $this->guardTender($req, $pricing);

            $this->redistributeRoundingToLatestOrder($orders, $pricing);

            foreach ($orders as $order) {
                $order->forceFill(['status' => OrderStatus::Paid])->save();
            }

            $tendered = $this->effectiveTendered($req, $pricing);
            $change = max(0, $tendered - $pricing->finalTotalMinor);

            $settlement = TableSessionSettlement::query()->create([
                'shop_id' => $req->shopId,
                'table_session_id' => (int) $session->id,
                'order_subtotal_minor' => $pricing->orderSubtotalMinor,
                'order_discount_applied_minor' => $pricing->orderDiscountAppliedMinor,
                'total_before_rounding_minor' => $pricing->totalBeforeRoundingMinor,
                'rounding_adjustment_minor' => $pricing->roundingAdjustmentMinor,
                'final_total_minor' => $pricing->finalTotalMinor,
                'tendered_minor' => $tendered,
                'change_minor' => $change,
                'payment_method' => $req->paymentMethod,
                'session_revision_at_settle' => (int) $session->session_revision,
                'settled_by_user_id' => $req->actorUserId,
                'settled_at' => now(),
                'print_bypassed' => $req->printBypassed,
                'bypass_reason' => $req->printBypassed ? $req->bypassReason : null,
                'bypassed_by_user_id' => $req->printBypassed ? $req->bypassedByUserId : null,
            ]);
            $this->debugSettleLog('action_settlement_created', [
                'trace_id' => $traceId,
                'settlement_id' => (int) $settlement->id,
                'table_session_id' => (int) $session->id,
                'final_total_minor' => $pricing->finalTotalMinor,
            ]);

            $session->forceFill([
                'status' => TableSessionStatus::Closed,
                'closed_at' => now(),
                'session_revision' => (int) $session->session_revision + 1,
            ])->save();
            $this->debugSettleLog('action_session_closed', [
                'trace_id' => $traceId,
                'table_session_id' => (int) $session->id,
                'new_revision' => (int) $session->session_revision,
            ]);

            return $settlement->refresh();
        });
    }

    private function repairSettledSessionState(
        FinalizeTableSettlementRequest $req,
        TableSessionSettlement $settlement,
        string $traceId
    ): TableSessionSettlement {
        $session = TableSession::query()
            ->whereKey($req->tableSessionId)
            ->where('shop_id', $req->shopId)
            ->lockForUpdate()
            ->first();

        if ($session === null) {
            throw new SessionAlreadySettledException($req->tableSessionId);
        }

        $needsSessionCloseRepair = $session->status !== TableSessionStatus::Closed || $session->closed_at === null;
        if ($needsSessionCloseRepair) {
            $session->forceFill([
                'status' => TableSessionStatus::Closed,
                'closed_at' => $session->closed_at ?? now(),
                'session_revision' => (int) $session->session_revision + 1,
            ])->save();
            $this->debugSettleLog('action_repair_closed_session', [
                'trace_id' => $traceId,
                'table_session_id' => (int) $session->id,
                'new_revision' => (int) $session->session_revision,
            ]);
        }

        $unpaidOrders = PosOrder::query()
            ->where('table_session_id', (int) $session->id)
            ->where('status', '!=', OrderStatus::Voided)
            ->where('status', '!=', OrderStatus::Paid)
            ->lockForUpdate()
            ->get();
        if ($unpaidOrders->isNotEmpty()) {
            $table = RestaurantTable::query()
                ->whereKey((int) $session->restaurant_table_id)
                ->where('shop_id', $req->shopId)
                ->lockForUpdate()
                ->first();
            if ($table === null) {
                throw new RuntimeException(__('pos.table_not_found'));
            }

            if ($session->status !== TableSessionStatus::Closed || $session->closed_at === null) {
                $session->forceFill([
                    'status' => TableSessionStatus::Closed,
                    'closed_at' => $session->closed_at ?? now(),
                    'session_revision' => (int) $session->session_revision + 1,
                ])->save();
            }

            $freshSession = $this->tableSessionLifecycleService->getOrCreateActiveSession($table);
            if ((int) $freshSession->id === (int) $session->id) {
                throw new RuntimeException('Settlement integrity error: failed to fork a fresh table session for unpaid orders.');
            }

            foreach ($unpaidOrders as $order) {
                $order->forceFill([
                    'table_session_id' => (int) $freshSession->id,
                ])->save();
            }

            $this->debugSettleLog('action_detect_unpaid_orders_on_settled_session', [
                'trace_id' => $traceId,
                'table_session_id' => (int) $session->id,
                'unpaid_order_count' => $unpaidOrders->count(),
                'moved_to_session_id' => (int) $freshSession->id,
            ]);

            // Complete settlement in one confirm flow:
            // immediately settle the freshly forked active session.
            return $this->execute(new FinalizeTableSettlementRequest(
                shopId: $req->shopId,
                tableSessionId: (int) $freshSession->id,
                expectedSessionRevision: (int) $freshSession->session_revision,
                tenderedMinor: $req->tenderedMinor,
                paymentMethod: $req->paymentMethod,
                actorUserId: $req->actorUserId,
                printBypassed: $req->printBypassed,
                bypassReason: $req->bypassReason,
                bypassedByUserId: $req->bypassedByUserId,
                debugTraceId: $traceId,
            ));
        }

        return $settlement->refresh();
    }

    /**
     * Apply payment-method-specific tender guards.
     * - Cash / Voucher: must cover the final total.
     * - Card: terminal charges exactly the final total (tendered is derived).
     * - BypassForced: manager-authorised; any tender is accepted.
     */
    private function guardTender(FinalizeTableSettlementRequest $req, PricingResult $pricing): void
    {
        if ($req->paymentMethod === PaymentMethod::Card || $req->paymentMethod === PaymentMethod::BypassForced) {
            return;
        }

        if ($req->tenderedMinor < $pricing->finalTotalMinor) {
            throw new InsufficientTenderException(
                tableSessionId: $req->tableSessionId,
                finalTotalMinor: $pricing->finalTotalMinor,
                tenderedMinor: $req->tenderedMinor,
            );
        }
    }

    /**
     * For Card payments we normalise tendered_minor to final_total_minor so
     * settlement snapshots are self-consistent across payment methods
     * (change_minor == 0 for card).
     */
    private function effectiveTendered(FinalizeTableSettlementRequest $req, PricingResult $pricing): int
    {
        if ($req->paymentMethod === PaymentMethod::Card) {
            return $pricing->finalTotalMinor;
        }

        return $req->tenderedMinor;
    }

    /**
     * Enforces invariant I5: SUM(orders.rounding_adjustment_minor) equals the
     * session-level rounding from PricingEngine.
     *
     * The contemporaneous per-order rounding values written by
     * AddPosOrderFromStaffAction are replaced with a session-canonical
     * distribution where:
     *   - all non-voided orders except the latest are zeroed, and
     *   - the latest non-voided order absorbs the entire session rounding.
     *
     * This keeps per-order aggregate queries correct without requiring
     * callers to consult the settlement table.
     *
     * @param  Collection<int, PosOrder>  $orders
     *                                             Ordered by created_at ASC, id ASC.
     */
    private function redistributeRoundingToLatestOrder($orders, PricingResult $pricing): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $latestId = (int) $orders->last()->id;

        foreach ($orders as $order) {
            $newRounding = ((int) $order->id === $latestId)
                ? $pricing->roundingAdjustmentMinor
                : 0;

            if ((int) ($order->rounding_adjustment_minor ?? 0) === $newRounding) {
                continue;
            }

            $order->forceFill(['rounding_adjustment_minor' => $newRounding])->save();
        }
    }

    // TEMP: POS_SETTLE_DEBUG
    private function debugSettleLog(string $event, array $context = []): void
    {
        if (! config('app.debug')) {
            return;
        }

        Log::info('POS_SETTLE_DEBUG '.$event, $context);
    }
}
