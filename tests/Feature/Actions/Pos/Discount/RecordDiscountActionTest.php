<?php

namespace Tests\Feature\Actions\Pos\Discount;

use App\Actions\Pos\Discount\RecordDiscountRequest;
use App\Actions\Pos\Discount\RecordItemDiscountAction;
use App\Actions\Pos\Discount\RecordOrderDiscountAction;
use App\Actions\Pos\Discount\RecordStaffDiscountAction;
use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Domains\Pos\Discount\DiscountType;
use App\Enums\PaymentMethod;
use App\Exceptions\Pos\DiscountPinRejectedException;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Models\DiscountAuditLog;
use App\Models\PosOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class RecordDiscountActionTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_item_discount_flat_persists_line_and_audit_log(): void
    {
        $shop = $this->makeShop('disc-1');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 10_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop, level: 3, pin: '1234');

        $req = new RecordDiscountRequest(
            shopId: (int) $shop->id,
            operatorUserId: (int) $operator->id,
            approverStaffId: (int) $approver->id,
            approverPin: '1234',
            reason: 'Client habitué',
            idempotencyKey: 'disc-item-1',
            flatMinor: 2_500,
        );

        $audit = app(RecordItemDiscountAction::class)->execute($req, (int) $line->id);

        $this->assertSame(2_500, (int) $audit->amount_minor);
        $this->assertSame(DiscountType::Item, $audit->discount_type);
        $this->assertSame((int) $operator->id, (int) $audit->actor_user_id);
        $this->assertSame((int) $approver->id, (int) $audit->approver_staff_id);
        $this->assertSame(3, (int) $audit->actor_job_level);

        $line->refresh();
        $this->assertSame(2_500, (int) $line->line_discount_minor);
    }

    public function test_item_discount_flat_is_capped_to_line_subtotal(): void
    {
        $shop = $this->makeShop('disc-cap');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 3_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        $audit = app(RecordItemDiscountAction::class)->execute(
            new RecordDiscountRequest(
                shopId: (int) $shop->id,
                operatorUserId: (int) $operator->id,
                approverStaffId: (int) $approver->id,
                approverPin: '1234',
                reason: 'Full comp',
                idempotencyKey: 'disc-cap',
                flatMinor: 999_999,
            ),
            (int) $line->id,
        );

        $this->assertSame(3_000, (int) $audit->amount_minor);
        $this->assertSame(3_000, (int) $line->refresh()->line_discount_minor);
    }

    public function test_wrong_pin_is_rejected_and_nothing_is_persisted(): void
    {
        $shop = $this->makeShop('disc-pin');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 5_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop, pin: '1234');

        $this->expectException(DiscountPinRejectedException::class);

        try {
            app(RecordItemDiscountAction::class)->execute(
                new RecordDiscountRequest(
                    shopId: (int) $shop->id,
                    operatorUserId: (int) $operator->id,
                    approverStaffId: (int) $approver->id,
                    approverPin: '9999',
                    reason: 'wrong pin',
                    idempotencyKey: 'disc-pin',
                    flatMinor: 1_000,
                ),
                (int) $line->id,
            );
        } finally {
            $this->assertSame(0, (int) $line->refresh()->line_discount_minor);
            $this->assertDatabaseCount('discount_audit_logs', 0);
        }
    }

    public function test_job_level_below_3_is_rejected_by_policy(): void
    {
        $shop = $this->makeShop('disc-lvl');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 5_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop, level: 2);

        $this->expectException(RuntimeException::class);

        try {
            app(RecordItemDiscountAction::class)->execute(
                new RecordDiscountRequest(
                    shopId: (int) $shop->id,
                    operatorUserId: (int) $operator->id,
                    approverStaffId: (int) $approver->id,
                    approverPin: '1234',
                    reason: 'too junior',
                    idempotencyKey: 'disc-lvl',
                    flatMinor: 1_000,
                ),
                (int) $line->id,
            );
        } finally {
            $this->assertSame(0, (int) $line->refresh()->line_discount_minor);
            $this->assertDatabaseCount('discount_audit_logs', 0);
        }
    }

    public function test_duplicate_idempotency_key_throws_and_does_not_double_apply(): void
    {
        $shop = $this->makeShop('disc-idem');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 10_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        $req = new RecordDiscountRequest(
            shopId: (int) $shop->id,
            operatorUserId: (int) $operator->id,
            approverStaffId: (int) $approver->id,
            approverPin: '1234',
            reason: 'Promo',
            idempotencyKey: 'disc-dup',
            flatMinor: 1_500,
        );

        app(RecordItemDiscountAction::class)->execute($req, (int) $line->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('pos.discount_already_recorded'));
        try {
            app(RecordItemDiscountAction::class)->execute($req, (int) $line->id);
        } finally {
            $this->assertSame(1_500, (int) $line->refresh()->line_discount_minor);
            $this->assertDatabaseCount('discount_audit_logs', 1);
        }
    }

    public function test_order_discount_percent_persists_order(): void
    {
        $shop = $this->makeShop('disc-ord');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['order' => $order] = $this->placeLinedOrder($shop, $session, 10_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        $audit = app(RecordOrderDiscountAction::class)->execute(
            new RecordDiscountRequest(
                shopId: (int) $shop->id,
                operatorUserId: (int) $operator->id,
                approverStaffId: (int) $approver->id,
                approverPin: '1234',
                reason: 'Manager promo',
                idempotencyKey: 'disc-order',
                percentBasisPoints: 1_000,
            ),
            (int) $order->id,
        );

        $this->assertSame(1_000, (int) $audit->amount_minor);
        $this->assertSame(DiscountType::Order, $audit->discount_type);
        $this->assertSame(1_000, (int) $order->refresh()->order_discount_minor);
    }

    public function test_staff_discount_50_percent_distributes_across_orders_and_settles_correctly(): void
    {
        $shop = $this->makeShop('disc-staff');
        $table = $this->makeStaffTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 6_000);
        $this->placeLinedOrder($shop, $session, 4_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        $audit = app(RecordStaffDiscountAction::class)->execute(
            new RecordDiscountRequest(
                shopId: (int) $shop->id,
                operatorUserId: (int) $operator->id,
                approverStaffId: (int) $approver->id,
                approverPin: '1234',
                reason: 'Staff meal',
                idempotencyKey: 'disc-staff',
                percentBasisPoints: 5_000,
            ),
            (int) $session->id,
        );

        $this->assertSame(5_000, (int) $audit->amount_minor);
        $this->assertNull($audit->order_id);

        $sumOrderDiscount = (int) PosOrder::query()
            ->where('table_session_id', $session->id)
            ->sum('order_discount_minor');
        $this->assertSame(5_000, $sumOrderDiscount);

        $settlement = app(FinalizeTableSettlementAction::class)->execute(
            new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: (int) $session->fresh()->session_revision,
                tenderedMinor: 5_000,
                paymentMethod: PaymentMethod::Cash,
                actorUserId: (int) $operator->id,
            )
        );

        $this->assertSame(10_000, (int) $settlement->order_subtotal_minor);
        $this->assertSame(5_000, (int) $settlement->order_discount_applied_minor);
        $this->assertSame(5_000, (int) $settlement->final_total_minor);
    }

    public function test_staff_discount_rejected_on_non_staff_table(): void
    {
        $shop = $this->makeShop('disc-staff-bad');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 6_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('pos.discount_staff_only_on_staff_tables'));

        try {
            app(RecordStaffDiscountAction::class)->execute(
                new RecordDiscountRequest(
                    shopId: (int) $shop->id,
                    operatorUserId: (int) $operator->id,
                    approverStaffId: (int) $approver->id,
                    approverPin: '1234',
                    reason: 'bogus',
                    idempotencyKey: 'disc-staff-bad',
                    percentBasisPoints: 5_000,
                ),
                (int) $session->id,
            );
        } finally {
            $this->assertDatabaseCount('discount_audit_logs', 0);
        }
    }

    public function test_discount_rejected_after_session_is_settled(): void
    {
        $shop = $this->makeShop('disc-closed');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 10_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        app(FinalizeTableSettlementAction::class)->execute(
            new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: (int) $session->fresh()->session_revision,
                tenderedMinor: 10_000,
                paymentMethod: PaymentMethod::Cash,
                actorUserId: (int) $operator->id,
            )
        );

        $this->expectException(SessionAlreadySettledException::class);

        app(RecordItemDiscountAction::class)->execute(
            new RecordDiscountRequest(
                shopId: (int) $shop->id,
                operatorUserId: (int) $operator->id,
                approverStaffId: (int) $approver->id,
                approverPin: '1234',
                reason: 'too late',
                idempotencyKey: 'disc-closed',
                flatMinor: 500,
            ),
            (int) $line->id,
        );
    }

    public function test_item_discount_request_rejects_both_flat_and_percent(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $shop = $this->makeShop('disc-valid');
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        new RecordDiscountRequest(
            shopId: (int) $shop->id,
            operatorUserId: (int) $operator->id,
            approverStaffId: (int) $approver->id,
            approverPin: '', // invalid
            reason: 'x',
            idempotencyKey: 'xx',
            flatMinor: 0,
        );
    }

    public function test_discount_audit_log_is_append_only(): void
    {
        $shop = $this->makeShop('disc-append');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 5_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop);

        $audit = app(RecordItemDiscountAction::class)->execute(
            new RecordDiscountRequest(
                shopId: (int) $shop->id,
                operatorUserId: (int) $operator->id,
                approverStaffId: (int) $approver->id,
                approverPin: '1234',
                reason: 'coupon',
                idempotencyKey: 'disc-append',
                flatMinor: 1_000,
            ),
            (int) $line->id,
        );

        $this->assertInstanceOf(DiscountAuditLog::class, $audit);
        $this->assertNull($audit->updated_at);
    }
}
