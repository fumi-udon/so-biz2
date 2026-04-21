<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enums\TableSessionStatus;
use App\Livewire\Pos\ClotureModal;
use App\Models\TableSessionSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class ClotureModalTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_opens_and_loads_pricing_from_session(): void
    {
        $shop = $this->makeShop('cloture-open');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 10_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch(
                'pos-cloture-open',
                shop_id: (int) $shop->id,
                table_session_id: (int) $session->id,
                expected_revision: (int) $session->session_revision,
            )
            ->assertSet('open', true)
            ->assertSet('subtotalMinor', 10_000)
            ->assertSet('finalTotalMinor', 10_000)
            ->assertSet('justeMinor', 10_000);
    }

    public function test_picking_card_sets_tendered_to_final_total(): void
    {
        $shop = $this->makeShop('cloture-card');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 7_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('pickPayment', 'card')
            ->assertSet('paymentMethod', 'card')
            ->assertSet('tenderedMinor', 7_000)
            ->assertSet('changeMinor', 0);
    }

    public function test_set_tendered_computes_change(): void
    {
        $shop = $this->makeShop('cloture-change');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 34_500);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('setTendered', 50_000)
            ->assertSet('changeMinor', 15_500);
    }

    public function test_confirm_cash_creates_settlement_without_immediate_print(): void
    {
        $shop = $this->makeShop('cloture-cash');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 24_000);
        $operator = $this->makeOperator();

        Livewire::actingAs($operator)
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('pickPayment', 'cash')
            ->call('setTendered', 30_000)
            ->call('confirm')
            ->assertDispatched('pos-settlement-completed')
            ->assertNotDispatched('pos-trigger-print');

        $this->assertDatabaseHas('table_session_settlements', [
            'table_session_id' => $session->id,
            'final_total_minor' => 24_000,
            'tendered_minor' => 30_000,
            'change_minor' => 6_000,
        ]);

        $this->assertDatabaseCount('print_jobs', 0);
    }

    public function test_confirm_blocked_when_insufficient_cash(): void
    {
        $shop = $this->makeShop('cloture-short');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 10_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('pickPayment', 'cash')
            ->call('setTendered', 5_000)
            ->call('confirm');

        $this->assertDatabaseCount('table_session_settlements', 0);
    }

    public function test_bypass_requires_manager_and_creates_bypass_settlement(): void
    {
        $shop = $this->makeShop('cloture-bypass');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 12_000);
        $operator = $this->makeOperator();
        $manager = $this->makeApprover($shop, level: 4, pin: '9999');

        Livewire::actingAs($operator)
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->set('bypassApproverStaffId', (int) $manager->id)
            ->set('bypassApproverPin', '9999')
            ->set('bypassReason', 'printer offline')
            ->call('confirmBypass')
            ->assertDispatched('pos-settlement-completed');

        $settlement = TableSessionSettlement::query()->where('table_session_id', $session->id)->firstOrFail();
        $this->assertTrue((bool) $settlement->print_bypassed);
        $this->assertSame('printer offline', $settlement->bypass_reason);
    }

    public function test_bypass_with_non_manager_pin_is_rejected(): void
    {
        $shop = $this->makeShop('cloture-bypass-lv3');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 12_000);
        $operator = $this->makeOperator();
        $lv3 = $this->makeApprover($shop, level: 3, pin: '1234');

        Livewire::actingAs($operator)
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->set('bypassApproverStaffId', (int) $lv3->id)
            ->set('bypassApproverPin', '1234')
            ->set('bypassReason', 'nope')
            ->call('confirmBypass');

        $this->assertDatabaseCount('table_session_settlements', 0);
    }

    public function test_other_shop_event_is_ignored(): void
    {
        $shop = $this->makeShop('cloture-other');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 1_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: 99_999, table_session_id: (int) $session->id, expected_revision: 0)
            ->assertSet('open', false);
    }

    public function test_closing_state_unmounts_session(): void
    {
        $shop = $this->makeShop('cloture-close');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 5_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('closeModal')
            ->assertSet('open', false)
            ->assertSet('tableSessionId', null);
    }

    public function test_session_marked_closed_after_confirm(): void
    {
        $shop = $this->makeShop('cloture-closed-status');
        $table = $this->makeCustomerTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 2_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('pickPayment', 'cash')
            ->call('setTendered', 2_000)
            ->call('confirm');

        $this->assertSame(TableSessionStatus::Closed, $session->fresh()->status);
    }
}
