<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\TableActionHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class TableActionHostStaffMealTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_opening_empty_staff_meal_table_does_not_create_session_until_pin(): void
    {
        $shop = $this->makeShop('staff-meal-gate');
        $this->makeStaffTable($shop, 101);
        $operator = $this->makeOperator('staff-meal-gate');
        $this->actingAs($operator);

        $c = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', 101, null);

        $this->assertSame(101, $c->get('activeRestaurantTableId'));
        $this->assertNull($c->get('activeTableSessionId'));
        $this->assertFalse((bool) $c->get('addModalOpen'));
        $this->assertTrue($c->instance()->requiresStaffMealAuth);
    }

    public function test_staff_meal_session_with_staff_name_skips_pin_and_does_not_auto_open_add_modal(): void
    {
        $shop = $this->makeShop('staff-meal-named');
        $table = $this->makeStaffTable($shop, 102);
        $session = $this->openActiveSession($shop, $table);
        $session->staff_name = 'Yamada';
        $session->save();

        $operator = $this->makeOperator('staff-meal-named');
        $this->actingAs($operator);

        Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', 102, (int) $session->id)
            ->assertSet('addModalOpen', false);
    }
}
