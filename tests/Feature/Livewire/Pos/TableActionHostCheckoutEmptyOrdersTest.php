<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\TableActionHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class TableActionHostCheckoutEmptyOrdersTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_cloture_disabled_and_checkout_blocked_when_session_has_no_orders(): void
    {
        $shop = $this->makeShop('host-checkout-empty');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $operator = $this->makeOperator('host-checkout-empty');

        Livewire::actingAs($operator)
            ->test(TableActionHost::class, ['shopId' => (int) $shop->id])
            ->call('onActionHostOpened', (int) $table->id, (int) $session->id)
            ->assertSet('canCloture', false)
            ->call('checkoutSession')
            ->assertNotDispatched('pos-cloture-open');
    }

    public function test_cloture_disabled_for_takeaway_session_with_no_orders(): void
    {
        $shop = $this->makeShop('host-checkout-empty-to');
        $table = $this->makeTakeawayTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $session->forceFill(['customer_name' => 'Walk-in'])->save();
        $operator = $this->makeOperator('host-checkout-empty-to');

        Livewire::actingAs($operator)
            ->test(TableActionHost::class, ['shopId' => (int) $shop->id])
            ->call('onActionHostOpened', (int) $table->id, (int) $session->id)
            ->assertSet('canCloture', false)
            ->call('checkoutSession')
            ->assertNotDispatched('pos-cloture-open');
    }
}
