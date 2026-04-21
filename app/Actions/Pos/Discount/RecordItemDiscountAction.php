<?php

namespace App\Actions\Pos\Discount;

use App\Domains\Pos\Discount\ApplyItemDiscount;
use App\Domains\Pos\Discount\DiscountType;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Models\DiscountAuditLog;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\Staff;
use App\Models\TableSession;
use App\Models\TableSessionSettlement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies a line-level discount (flat minor OR percentage basis points) to a
 * single {@see OrderLine}, writing both the effective `line_discount_minor`
 * and an append-only row in `discount_audit_logs`.
 *
 * Guarantees:
 *  - PIN verification + DiscountPolicy::assertAuthorized *before* any write.
 *  - DB unique(idempotency_key) on audit log prevents double-recording if
 *    the UI submits twice.
 *  - Row-level locks (lockForUpdate) on order and line prevent TOCTOU with
 *    concurrent edits/settlements.
 *  - Throws SessionAlreadySettledException if the parent session has
 *    already been settled (no discount after the fact).
 */
final class RecordItemDiscountAction
{
    public function __construct(
        private readonly ApplyItemDiscount $applyItemDiscount,
        private readonly DiscountAuthorizer $authorizer,
    ) {}

    public function execute(RecordDiscountRequest $req, int $orderLineId): DiscountAuditLog
    {
        $ctx = $this->authorizer->verifyAndBuildContext($req, DiscountType::Item);

        return DB::transaction(function () use ($req, $orderLineId, $ctx): DiscountAuditLog {
            $line = OrderLine::query()
                ->where('shop_id', $req->shopId)
                ->whereKey($orderLineId)
                ->lockForUpdate()
                ->first();

            if ($line === null) {
                throw new RuntimeException(__('pos.discount_target_not_found'));
            }

            $order = PosOrder::query()
                ->where('shop_id', $req->shopId)
                ->whereKey($line->order_id)
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw new RuntimeException(__('pos.discount_target_not_found'));
            }

            $this->assertSessionNotSettled((int) $order->table_session_id);

            $basis = (int) $line->line_total_minor - (int) ($line->line_discount_minor ?? 0);
            $basis = max(0, $basis);

            $amount = $this->applyItemDiscount->execute(
                lineSubtotalMinor: $basis,
                ctx: $ctx,
                flatMinor: $req->flatMinor,
                percentBasisPoints: $req->percentBasisPoints,
            );

            $line->forceFill([
                'line_discount_minor' => (int) ($line->line_discount_minor ?? 0) + $amount,
            ])->save();

            return $this->writeAudit(
                $req,
                (int) $order->table_session_id,
                (int) $order->id,
                (int) $line->id,
                DiscountType::Item,
                $basis,
                $amount,
            );
        });
    }

    private function assertSessionNotSettled(int $sessionId): void
    {
        if (TableSessionSettlement::query()->where('table_session_id', $sessionId)->exists()) {
            throw new SessionAlreadySettledException($sessionId);
        }

        $session = TableSession::query()->whereKey($sessionId)->first();
        if ($session === null) {
            throw new RuntimeException(__('pos.discount_target_not_found'));
        }
    }

    private function writeAudit(
        RecordDiscountRequest $req,
        int $sessionId,
        int $orderId,
        ?int $orderLineId,
        DiscountType $type,
        int $basis,
        int $amount,
    ): DiscountAuditLog {
        try {
            return DiscountAuditLog::query()->create([
                'shop_id' => $req->shopId,
                'table_session_id' => $sessionId,
                'order_id' => $orderId,
                'order_line_id' => $orderLineId,
                'discount_type' => $type,
                'basis_minor' => $basis,
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
    }

    private function resolveApproverJobLevel(RecordDiscountRequest $req): int
    {
        $staff = Staff::query()->with('jobLevel')->whereKey($req->approverStaffId)->first();

        return (int) ($staff?->jobLevel?->level ?? 0);
    }
}
