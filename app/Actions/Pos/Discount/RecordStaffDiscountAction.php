<?php

namespace App\Actions\Pos\Discount;

use App\Domains\Pos\Discount\ApplyStaffDiscount;
use App\Domains\Pos\Discount\DiscountType;
use App\Domains\Pos\Tables\TableCategory;
use App\Enums\OrderStatus;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Models\DiscountAuditLog;
use App\Models\PosOrder;
use App\Models\Staff;
use App\Models\TableSession;
use App\Models\TableSessionSettlement;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Applies the fixed-rate (50 %) staff discount to every non-voided order in
 * a session whose table category is Staff (IDs 100-109). The computed
 * session-level discount amount is **distributed proportionally** across
 * all non-voided orders (rounding remainders fall onto the most recent
 * order), preserving the invariant:
 *
 *   SUM(orders.order_discount_minor for this session) == audit.amount_minor
 *
 * Only one audit row is written per application (order_id is NULL since the
 * discount is session-scoped); `idempotency_key` remains UNIQUE so the UI
 * cannot accidentally apply "staff 50%" twice to the same session.
 */
final class RecordStaffDiscountAction
{
    public function __construct(
        private readonly ApplyStaffDiscount $applyStaffDiscount,
        private readonly DiscountAuthorizer $authorizer,
    ) {}

    public function execute(RecordDiscountRequest $req, int $tableSessionId): DiscountAuditLog
    {
        $ctx = $this->authorizer->verifyAndBuildContext($req, DiscountType::Staff);

        return DB::transaction(function () use ($req, $tableSessionId, $ctx): DiscountAuditLog {
            $session = TableSession::query()
                ->where('shop_id', $req->shopId)
                ->whereKey($tableSessionId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new RuntimeException(__('pos.discount_target_not_found'));
            }

            if (TableSessionSettlement::query()->where('table_session_id', $session->id)->exists()) {
                throw new SessionAlreadySettledException((int) $session->id);
            }

            $category = TableCategory::tryResolveFromId((int) $session->restaurant_table_id);
            if ($category !== TableCategory::Staff) {
                throw new RuntimeException(__('pos.discount_staff_only_on_staff_tables'));
            }

            /** @var Collection<int, PosOrder> $orders */
            $orders = PosOrder::query()
                ->where('shop_id', $req->shopId)
                ->where('table_session_id', $session->id)
                ->where('status', '!=', OrderStatus::Voided)
                ->orderBy('created_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $subtotal = 0;
            foreach ($orders as $o) {
                $subtotal += max(0, (int) $o->total_price_minor - (int) ($o->order_discount_minor ?? 0));
            }

            $amount = $this->applyStaffDiscount->execute(
                orderSubtotalMinor: $subtotal,
                category: $category,
                ctx: $ctx,
            );

            $this->distributeToOrders($orders, $amount);

            try {
                return DiscountAuditLog::query()->create([
                    'shop_id' => $req->shopId,
                    'table_session_id' => (int) $session->id,
                    'order_id' => null,
                    'order_line_id' => null,
                    'discount_type' => DiscountType::Staff,
                    'basis_minor' => $subtotal,
                    'amount_minor' => $amount,
                    'percent_basis_points' => ApplyStaffDiscount::STAFF_DISCOUNT_BP,
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

    /**
     * Distribute a session-level discount across orders proportionally to
     * their current net subtotal. Integer fair-share: first pass floors,
     * any remainder is absorbed by the latest order so SUM is exact.
     *
     * @param  Collection<int, PosOrder>  $orders
     */
    private function distributeToOrders($orders, int $totalDiscount): void
    {
        if ($totalDiscount <= 0 || $orders->isEmpty()) {
            return;
        }

        $basisTotal = 0;
        $basises = [];
        foreach ($orders as $o) {
            $b = max(0, (int) $o->total_price_minor - (int) ($o->order_discount_minor ?? 0));
            $basises[(int) $o->id] = $b;
            $basisTotal += $b;
        }

        if ($basisTotal <= 0) {
            return;
        }

        $assigned = 0;
        $count = $orders->count();
        foreach ($orders->values() as $idx => $o) {
            $isLast = $idx === ($count - 1);
            if ($isLast) {
                $share = $totalDiscount - $assigned;
            } else {
                $share = intdiv($basises[(int) $o->id] * $totalDiscount, $basisTotal);
            }
            $assigned += $share;

            if ($share > 0) {
                $o->forceFill([
                    'order_discount_minor' => (int) ($o->order_discount_minor ?? 0) + $share,
                ])->save();
            }
        }
    }

    private function resolveApproverJobLevel(RecordDiscountRequest $req): int
    {
        $staff = Staff::query()->with('jobLevel')->whereKey($req->approverStaffId)->first();

        return (int) ($staff?->jobLevel?->level ?? 0);
    }
}
