<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enums\OrderStatus;
use App\Enums\PrintIntent;
use App\Livewire\Pos\TableActionHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class TableActionHostPrintPreviewTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_print_addition_opens_receipt_preview_when_session_has_only_confirmed_orders(): void
    {
        $shop = $this->makeShop('host-print-preview');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 4_200, OrderStatus::Confirmed);
        $operator = $this->makeOperator('host-print-preview');

        Livewire::actingAs($operator)
            ->test(TableActionHost::class, ['shopId' => (int) $shop->id])
            ->call('onActionHostOpened', (int) $table->id, (int) $session->id)
            ->call('printAddition')
            ->assertSet('showReceiptPreview', true)
            ->assertSet('previewIntent', PrintIntent::Addition->value)
            ->assertSet('previewSessionId', (int) $session->id);
    }
}
