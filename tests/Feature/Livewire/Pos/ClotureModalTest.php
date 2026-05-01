<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enums\TableSessionStatus;
use App\Livewire\Pos\ClotureModal;
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

    public function test_set_tendered_computes_change(): void
    {
        $shop = $this->makeShop('cloture-change');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 34_500);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('setTendered', 50_000)
            ->assertSet('changeMinor', 15_500)
            ->assertSet('tenderedDtInput', '50');
    }

    public function test_confirm_parses_tendered_dt_input(): void
    {
        $shop = $this->makeShop('cloture-dt-input');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 10_000);

        Livewire::actingAs($this->makeOperator())
            ->test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->set('tenderedDtInput', '12.5')
            ->call('confirm')
            ->assertDispatched('pos-settlement-completed');

        $this->assertDatabaseHas('table_session_settlements', [
            'table_session_id' => $session->id,
            'final_total_minor' => 10_000,
            'tendered_minor' => 12_500,
            'change_minor' => 2_500,
        ]);
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
            ->call('setTendered', 5_000)
            ->call('confirm');

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
            ->call('setTendered', 2_000)
            ->call('confirm');

        $this->assertSame(TableSessionStatus::Closed, $session->fresh()->status);
    }

    public function test_confirm_uses_pos2_settlement_actor_config_when_no_web_auth(): void
    {
        $shop = $this->makeShop('cloture-pos2-actor');
        $operator = $this->makeOperator();
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        $this->placeLinedOrder($shop, $session, 5_000);

        config(['app.pos2_settlement_actor_user_id' => (int) $operator->id]);

        Livewire::test(ClotureModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-cloture-open', shop_id: (int) $shop->id, table_session_id: (int) $session->id, expected_revision: (int) $session->session_revision)
            ->call('setTendered', 5_000)
            ->call('confirm')
            ->assertDispatched('pos-settlement-completed');

        $this->assertDatabaseHas('table_session_settlements', [
            'table_session_id' => $session->id,
        ]);
    }
}
