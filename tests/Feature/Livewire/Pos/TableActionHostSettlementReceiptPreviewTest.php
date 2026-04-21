<?php

namespace Tests\Feature\Livewire\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use App\Actions\Pos\FinalizeTableSettlementRequest;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrintIntent;
use App\Livewire\Pos\TableActionHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class TableActionHostSettlementReceiptPreviewTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_settlement_completed_with_open_receipt_preview_shows_facture_overlay(): void
    {
        $shop = $this->makeShop('host-settle-preview');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 9_000, OrderStatus::Confirmed);
        $operator = $this->makeOperator('host-settle-preview');

        $component = Livewire::actingAs($operator)
            ->test(TableActionHost::class, ['shopId' => (int) $shop->id])
            ->call('onActionHostOpened', (int) $table->id, (int) $session->id);

        $session = $session->fresh();
        app(FinalizeTableSettlementAction::class)->execute(
            new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: (int) $session->session_revision,
                tenderedMinor: 9_000,
                paymentMethod: PaymentMethod::Card,
                actorUserId: (int) $operator->id,
            )
        );

        $component
            ->dispatch('pos-settlement-completed', table_session_id: (int) $session->id, open_receipt_preview: true)
            ->assertSet('showReceiptPreview', true)
            ->assertSet('previewIntent', PrintIntent::Receipt->value)
            ->assertSet('previewSessionId', (int) $session->id);
    }

    public function test_settlement_completed_without_receipt_preview_closes_host(): void
    {
        $shop = $this->makeShop('host-settle-close');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 4_000, OrderStatus::Confirmed);
        $operator = $this->makeOperator('host-settle-close');

        $component = Livewire::actingAs($operator)
            ->test(TableActionHost::class, ['shopId' => (int) $shop->id])
            ->call('onActionHostOpened', (int) $table->id, (int) $session->id);

        $session = $session->fresh();
        app(FinalizeTableSettlementAction::class)->execute(
            new FinalizeTableSettlementRequest(
                shopId: (int) $shop->id,
                tableSessionId: (int) $session->id,
                expectedSessionRevision: (int) $session->session_revision,
                tenderedMinor: 4_000,
                paymentMethod: PaymentMethod::Card,
                actorUserId: (int) $operator->id,
            )
        );

        $component
            ->dispatch('pos-settlement-completed', table_session_id: (int) $session->id, open_receipt_preview: false)
            ->assertSet('activeTableSessionId', null);
    }
}
