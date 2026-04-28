<?php

namespace Tests\Feature\Actions\Pos;

use App\Actions\Pos\AddPosOrderFromStaffAction;
use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableSessionStatus;
use App\Models\PosOrder;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class AddPosOrderFromStaffActionSettledSessionGuardTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_add_order_creates_new_session_when_active_session_is_already_settled(): void
    {
        $shop = $this->makeShop('add-guard');
        $table = $this->makeCustomerTable($shop, 26);
        $session = $this->openActiveSession($shop, $table);
        $seed = $this->placeLinedOrder($shop, $session, 9_000, OrderStatus::Confirmed);
        $operator = $this->makeOperator('add-guard');

        app(FinalizeTableSettlementAction::class)->execute(
            new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: (int) $session->fresh()->session_revision,
                tenderedMinor: 9_000,
                paymentMethod: PaymentMethod::Cash,
                actorUserId: (int) $operator->id,
            )
        );

        // Simulate a drifted row observed in production: settled snapshot exists
        // while the session status remains Active.
        $session->refresh();
        $session->forceFill([
            'status' => TableSessionStatus::Active,
            'closed_at' => null,
        ])->save();

        $menuItemId = (int) $seed['line']->menu_item_id;
        $newOrderId = app(AddPosOrderFromStaffAction::class)->execute(
            shopId: (int) $shop->id,
            restaurantTableId: (int) $table->id,
            menuItemId: $menuItemId,
            qty: 1,
            styleId: null,
            toppingIds: [],
            note: 'guard',
        );

        $newOrder = PosOrder::query()->whereKey($newOrderId)->firstOrFail();
        $this->assertNotSame((int) $session->id, (int) $newOrder->table_session_id);

        $drifted = TableSession::query()->whereKey($session->id)->firstOrFail();
        $this->assertSame(TableSessionStatus::Closed, $drifted->status);
        $this->assertNotNull($drifted->closed_at);
    }
}

