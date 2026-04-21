<?php

namespace Tests\Feature\Actions\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableSessionStatus;
use App\Exceptions\Pos\InsufficientTenderException;
use App\Exceptions\Pos\PendingOrdersRemainException;
use App\Exceptions\Pos\SessionAlreadySettledException;
use App\Exceptions\RevisionConflictException;
use App\Models\PosOrder;
use App\Models\TableSession;
use App\Models\TableSessionSettlement;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class FinalizeTableSettlementActionTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create();
    }

    private function action(): FinalizeTableSettlementAction
    {
        return app(FinalizeTableSettlementAction::class);
    }

    public function test_happy_path_single_order_exact_cash_no_rounding(): void
    {
        $shop = $this->makeShop('fin-1');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 34_500, OrderStatus::Confirmed);

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: (int) $session->session_revision,
            tenderedMinor: 34_500,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(34_500, $settlement->final_total_minor);
        $this->assertSame(34_500, $settlement->total_before_rounding_minor);
        $this->assertSame(0, $settlement->rounding_adjustment_minor);
        $this->assertSame(0, $settlement->change_minor);
        $this->assertSame(34_500, $settlement->tendered_minor);
        $this->assertSame(PaymentMethod::Cash, $settlement->payment_method);
        $this->assertFalse((bool) $settlement->print_bypassed);

        $session->refresh();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
        $this->assertNotNull($session->closed_at);
        $this->assertSame(1, (int) $session->session_revision);

        $this->assertSame(OrderStatus::Paid, PosOrder::query()->where('table_session_id', $session->id)->firstOrFail()->status);
    }

    public function test_single_order_with_cents_triggers_floor_rounding_to_0_1_tnd(): void
    {
        $shop = $this->makeShop('fin-2');
        $table = $this->makeCustomerTable($shop, 11);
        $session = $this->openActiveSession($shop, $table);
        // 12 340 minor = 12.34 TND → floor to 12.3 TND (12 300 minor), rounding 40
        $this->placeLinedOrder($shop, $session, 12_340, OrderStatus::Confirmed);

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 20_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(12_340, $settlement->total_before_rounding_minor);
        $this->assertSame(40, $settlement->rounding_adjustment_minor);
        $this->assertSame(12_300, $settlement->final_total_minor);
        $this->assertSame(20_000 - 12_300, $settlement->change_minor);

        $orderRounding = (int) PosOrder::query()
            ->where('table_session_id', $session->id)
            ->sum('rounding_adjustment_minor');
        $this->assertSame(40, $orderRounding, 'SUM(orders.rounding_adjustment_minor) must equal settlement rounding');
    }

    public function test_multi_order_session_sums_rounding_onto_latest_order(): void
    {
        $shop = $this->makeShop('fin-3');
        $table = $this->makeCustomerTable($shop, 12);
        $session = $this->openActiveSession($shop, $table);

        $first = $this->placeLinedOrder($shop, $session, 5_670, OrderStatus::Confirmed);
        $second = $this->placeLinedOrder($shop, $session, 2_680, OrderStatus::Confirmed);
        // Subtotal 8350 → floor 8300, rounding 50.

        // Sanity: AddPosOrderFromStaffAction-style default is rounding=0; let's
        // seed non-zero values on both orders to prove the Action reassigns them.
        $first['order']->forceFill(['rounding_adjustment_minor' => 70])->save();
        $second['order']->forceFill(['rounding_adjustment_minor' => 30])->save();

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 10_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(8_350, $settlement->total_before_rounding_minor);
        $this->assertSame(50, $settlement->rounding_adjustment_minor);
        $this->assertSame(8_300, $settlement->final_total_minor);

        $first['order']->refresh();
        $second['order']->refresh();
        $this->assertSame(0, (int) $first['order']->rounding_adjustment_minor);
        $this->assertSame(50, (int) $second['order']->rounding_adjustment_minor);

        $orderSum = (int) PosOrder::query()
            ->where('table_session_id', $session->id)
            ->sum('rounding_adjustment_minor');
        $this->assertSame($settlement->rounding_adjustment_minor, $orderSum);
    }

    public function test_line_and_order_discounts_are_applied_in_engine_order(): void
    {
        $shop = $this->makeShop('fin-4');
        $table = $this->makeCustomerTable($shop, 13);
        $session = $this->openActiveSession($shop, $table);

        // Line 10 000, line_discount 2 000 → line net 8 000
        $this->placeLinedOrder($shop, $session, 10_000, OrderStatus::Confirmed, 1, 2_000, 0);
        // Separate order, 5 000, no line discount, order_discount 500
        $this->placeLinedOrder($shop, $session, 5_000, OrderStatus::Confirmed, 1, 0, 500);

        // Session pricing:
        //   orderSubtotal = (10000-2000) + (5000-0) = 13000
        //   orderDiscount = 500 (sum of per-order discounts)
        //   totalBeforeRounding = 12500
        //   finalTotal (floor 100) = 12500, rounding = 0
        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 15_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(13_000, $settlement->order_subtotal_minor);
        $this->assertSame(500, $settlement->order_discount_applied_minor);
        $this->assertSame(12_500, $settlement->total_before_rounding_minor);
        $this->assertSame(0, $settlement->rounding_adjustment_minor);
        $this->assertSame(12_500, $settlement->final_total_minor);
        $this->assertSame(15_000 - 12_500, $settlement->change_minor);
    }

    public function test_card_payment_normalises_tendered_to_final(): void
    {
        $shop = $this->makeShop('fin-5');
        $table = $this->makeCustomerTable($shop, 14);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 7_800, OrderStatus::Confirmed);

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 0,
            paymentMethod: PaymentMethod::Card,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(7_800, $settlement->final_total_minor);
        $this->assertSame(7_800, $settlement->tendered_minor);
        $this->assertSame(0, $settlement->change_minor);
    }

    public function test_insufficient_cash_tender_rejected(): void
    {
        $shop = $this->makeShop('fin-6');
        $table = $this->makeCustomerTable($shop, 15);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 9_000, OrderStatus::Confirmed);

        $user = $this->actor();

        $this->expectException(InsufficientTenderException::class);

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 5_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertDatabaseMissing('table_session_settlements', ['table_session_id' => $session->id]);
    }

    public function test_pending_placed_orders_block_settlement(): void
    {
        $shop = $this->makeShop('fin-7');
        $table = $this->makeCustomerTable($shop, 16);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 3_000, OrderStatus::Placed); // unacked

        $user = $this->actor();

        $this->expectException(PendingOrdersRemainException::class);

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 3_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));
    }

    public function test_revision_mismatch_rejected(): void
    {
        $shop = $this->makeShop('fin-8');
        $table = $this->makeCustomerTable($shop, 17);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 3_000, OrderStatus::Confirmed);

        $session->forceFill(['session_revision' => 5])->save();

        $user = $this->actor();

        $this->expectException(RevisionConflictException::class);

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 3,
            tenderedMinor: 3_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));
    }

    public function test_already_settled_session_is_rejected(): void
    {
        $shop = $this->makeShop('fin-9');
        $table = $this->makeCustomerTable($shop, 18);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 3_000, OrderStatus::Confirmed);

        $user = $this->actor();

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 3_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->expectException(SessionAlreadySettledException::class);

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 1,
            tenderedMinor: 3_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $count = TableSessionSettlement::query()
            ->where('table_session_id', $session->id)
            ->count();
        $this->assertSame(1, $count, 'A second settlement row must never be created');
    }

    public function test_simultaneous_checkout_second_call_sees_stale_revision_and_fails(): void
    {
        // Simulate the "two tablets hit 'Clôturer' at once" race: after the
        // first finalize commits, the second call carries the now-stale
        // revision=0 and must be rejected. The unique constraint on
        // table_session_settlements.table_session_id is the ultimate belt.
        $shop = $this->makeShop('fin-10');
        $table = $this->makeCustomerTable($shop, 19);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 3_000, OrderStatus::Confirmed);

        $user = $this->actor();

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 3_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        try {
            $this->action()->execute(new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: 0,
                tenderedMinor: 3_000,
                paymentMethod: PaymentMethod::Cash,
                actorUserId: (int) $user->id,
            ));
            $this->fail('Expected the second finalize to throw.');
        } catch (SessionAlreadySettledException $e) {
            // Expected: second call hits the pre-flight dedupe.
        } catch (RevisionConflictException $e) {
            // Also acceptable if the Action ordering ever flips: proves the
            // optimistic lock catches the race.
        }

        $this->assertSame(
            1,
            TableSessionSettlement::query()->where('table_session_id', $session->id)->count(),
            'Race must never produce two settlement rows',
        );

        $session->refresh();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
    }

    public function test_bypass_forced_accepts_any_tender_and_records_reason(): void
    {
        $shop = $this->makeShop('fin-11');
        $table = $this->makeCustomerTable($shop, 20);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 7_600, OrderStatus::Confirmed);

        $manager = $this->actor();
        $cashier = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 0, // printer-down + customer already walked: no tender recorded
            paymentMethod: PaymentMethod::BypassForced,
            actorUserId: (int) $cashier->id,
            printBypassed: true,
            bypassReason: 'Printer out of paper, customer already paid cash offline',
            bypassedByUserId: (int) $manager->id,
        ));

        $this->assertTrue((bool) $settlement->print_bypassed);
        $this->assertSame('Printer out of paper, customer already paid cash offline', $settlement->bypass_reason);
        $this->assertSame((int) $manager->id, (int) $settlement->bypassed_by_user_id);
        $this->assertSame(PaymentMethod::BypassForced, $settlement->payment_method);

        $session->refresh();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
    }

    public function test_zero_total_session_settles_cleanly(): void
    {
        // A cancelled/empty session may have no lines. Should still close cleanly.
        $shop = $this->makeShop('fin-12');
        $table = $this->makeCustomerTable($shop, 21);
        $session = $this->openActiveSession($shop, $table);

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 0,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(0, $settlement->final_total_minor);
        $this->assertSame(0, $settlement->rounding_adjustment_minor);
        $session->refresh();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
    }

    public function test_voided_orders_are_excluded_from_session_pricing(): void
    {
        $shop = $this->makeShop('fin-13');
        $table = $this->makeCustomerTable($shop, 22);
        $session = $this->openActiveSession($shop, $table);

        $this->placeLinedOrder($shop, $session, 5_000, OrderStatus::Confirmed);
        $this->placeLinedOrder($shop, $session, 9_999_999, OrderStatus::Voided); // would break math if included

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 5_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(5_000, $settlement->final_total_minor);
    }

    public function test_settlement_snapshot_is_idempotent_against_unique_constraint(): void
    {
        // Last line of defense: even if two threads bypass the pre-flight
        // check somehow, the DB unique(table_session_id) must hold.
        $shop = $this->makeShop('fin-14');
        $table = $this->makeCustomerTable($shop, 23);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 2_000, OrderStatus::Confirmed);

        $user = $this->actor();

        $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 0,
            tenderedMinor: 2_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        // Manually attempt to insert a duplicate; must fail at the DB level.
        $this->expectException(QueryException::class);
        TableSessionSettlement::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'order_subtotal_minor' => 0,
            'order_discount_applied_minor' => 0,
            'total_before_rounding_minor' => 0,
            'rounding_adjustment_minor' => 0,
            'final_total_minor' => 0,
            'tendered_minor' => 0,
            'change_minor' => 0,
            'payment_method' => PaymentMethod::Cash,
            'session_revision_at_settle' => 999,
            'settled_by_user_id' => $user->id,
            'settled_at' => now(),
            'print_bypassed' => false,
        ]);
    }

    public function test_session_revision_is_preserved_in_settlement_snapshot(): void
    {
        $shop = $this->makeShop('fin-15');
        $table = $this->makeCustomerTable($shop, 24);
        $session = $this->openActiveSession($shop, $table);
        $session->forceFill(['session_revision' => 7])->save();
        $this->placeLinedOrder($shop, $session, 5_000, OrderStatus::Confirmed);

        $user = $this->actor();

        $settlement = $this->action()->execute(new FinalizeTableSettlementRequest(
            shopId: (int) $shop->id,
            tableSessionId: (int) $session->id,
            expectedSessionRevision: 7,
            tenderedMinor: 5_000,
            paymentMethod: PaymentMethod::Cash,
            actorUserId: (int) $user->id,
        ));

        $this->assertSame(7, (int) $settlement->session_revision_at_settle);

        /** @var TableSession $refreshed */
        $refreshed = TableSession::query()->findOrFail($session->id);
        $this->assertSame(8, (int) $refreshed->session_revision);
    }
}
