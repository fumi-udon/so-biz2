<?php

namespace App\Actions\Pos\Discount;

use App\Domains\Pos\Discount\ApplyOrderDiscount;
use App\Domains\Pos\Discount\DiscountType;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Models\DiscountAuditLog;
use App\Models\PosOrder;
use App\Models\Staff;
use App\Models\TableSessionSettlement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies an order-level discount (flat or percent) to a single PosOrder.
 * Semantics mirror {@see RecordItemDiscountAction} but at order granularity.
 *
 * Discounts are additive: calling twice with different reasons stacks the
 * amounts (each write has its own audit row). The PricingEngine invariant
 * min(orderSubtotal, orderDiscount) is enforced at settlement time, so
 * over-discounting cannot produce a negative final total.
 */
final class RecordOrderDiscountAction
{
    public function __construct(
        private readonly ApplyOrderDiscount $applyOrderDiscount,
        private readonly DiscountAuthorizer $authorizer,
    ) {}

    public function execute(RecordDiscountRequest $req, int $orderId): DiscountAuditLog
    {
        $ctx = $this->authorizer->verifyAndBuildContext($req, DiscountType::Order);

        return DB::transaction(function () use ($req, $orderId, $ctx): DiscountAuditLog {
            $order = PosOrder::query()
                ->where('shop_id', $req->shopId)
                ->whereKey($orderId)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new RuntimeException(__('pos.discount_target_not_found'));
            }

            $this->assertSessionNotSettled((int) $order->table_session_id);

            $orderSubtotal = (int) $order->total_price_minor - (int) ($order->order_discount_minor ?? 0);
            $orderSubtotal = max(0, $orderSubtotal);

            $amount = $this->applyOrderDiscount->execute(
                orderSubtotalMinor: $orderSubtotal,
                ctx: $ctx,
                flatMinor: $req->flatMinor,
                percentBasisPoints: $req->percentBasisPoints,
            );

            $order->forceFill([
                'order_discount_minor' => (int) ($order->order_discount_minor ?? 0) + $amount,
            ])->save();

            try {
                return DiscountAuditLog::query()->create([
                    'shop_id' => $req->shopId,
                    'table_session_id' => (int) $order->table_session_id,
                    'order_id' => (int) $order->id,
                    'order_line_id' => null,
                    'discount_type' => DiscountType::Order,
                    'basis_minor' => $orderSubtotal,
                    'amount_minor' => $amount,
                    'percent_basis_points' => $req->percentBasisPoints,
                    'actor_user_id' => $req->operatorUserId,
                    'actor_job_level' => $this->resolveApproverJobLevel($req),
                    'approver_staff_id' => $req->approverStaffId,
                    'reason' => $req->reason,
                    'idempotency_key' => $req->idempotencyKey,
                ]);
            } catch (QueryException $e) {
                if ((int) ($e->errorInfo[1] ?? 0) === 1062 || str_contains((string) $e->getMessage(), 'UNIQUE')) {
                    throw new RuntimeException(__('pos.discount_already_recorded'));
                }
                throw $e;
            }
        });
    }

    private function assertSessionNotSettled(int $sessionId): void
    {
        if (TableSessionSettlement::query()->where('table_session_id', $sessionId)->exists()) {
            throw new SessionAlreadySettledException($sessionId);
        }
    }

    private function resolveApproverJobLevel(RecordDiscountRequest $req): int
    {
        $staff = Staff::query()->with('jobLevel')->whereKey($req->approverStaffId)->first();

        return (int) ($staff?->jobLevel?->level ?? 0);
    }
}
