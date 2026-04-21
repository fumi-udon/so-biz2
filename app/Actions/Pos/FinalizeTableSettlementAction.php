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
use App\Support\Pos\StaffTableSettlementPricing;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
    ) {}

    public function execute(FinalizeTableSettlementRequest $req): TableSessionSettlement
    {
        return DB::transaction(function () use ($req): TableSessionSettlement {
            // I1 early-out: if a settlement already exists, fail fast with a
            // friendly error rather than deferring to the unique-violation
            // thrown by the DB during insert. Cheaper than a failed INSERT.
            if (TableSessionSettlement::query()
                ->where('table_session_id', $req->tableSessionId)
                ->exists()
            ) {
                throw new SessionAlreadySettledException($req->tableSessionId);
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

            if ((int) $session->session_revision !== $req->expectedSessionRevision) {
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

            $session->forceFill([
                'status' => TableSessionStatus::Closed,
                'closed_at' => now(),
                'session_revision' => (int) $session->session_revision + 1,
            ])->save();

            return $settlement->refresh();
        });
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
}
