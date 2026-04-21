<?php

namespace Tests\Feature\Livewire\Pos;

use App\Livewire\Pos\DiscountModal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class DiscountModalTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_opens_for_item_scope_and_applies_flat_discount(): void
    {
        $shop = $this->makeShop('dm-item');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 8_000);
        $operator = $this->makeOperator();
        $approver = $this->makeApprover($shop, level: 3, pin: '1234');

        Livewire::actingAs($operator)
            ->test(DiscountModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-discount-open', shop_id: (int) $shop->id, scope: 'item', target_id: (int) $line->id)
            ->set('approverStaffId', (int) $approver->id)
            ->set('approverPin', '1234')
            ->set('reason', 'test')
            ->set('mode', 'flat')
            ->set('flatMinor', 1_000)
            ->call('submit')
            ->assertDispatched('pos-discount-applied')
            ->assertSet('open', false);

        $this->assertSame(1_000, (int) $line->fresh()->line_discount_minor);
        $this->assertDatabaseCount('discount_audit_logs', 1);
    }

    public function test_staff_scope_opens_preloaded_with_50_percent(): void
    {
        $shop = $this->makeShop('dm-staff');
        $table = $this->makeStaffTable($shop);
        $session = $this->openActiveSession($shop, $table);
        $this->placeLinedOrder($shop, $session, 10_000);

        Livewire::actingAs($this->makeOperator())
            ->test(DiscountModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-discount-open', shop_id: (int) $shop->id, scope: 'staff', target_id: (int) $session->id)
            ->assertSet('scope', 'staff')
            ->assertSet('mode', 'percent')
            ->assertSet('percentBasisPoints', 5_000);
    }

    public function test_wrong_pin_leaves_modal_failed_without_db_write(): void
    {
        $shop = $this->makeShop('dm-pin');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 5_000);
        $approver = $this->makeApprover($shop, pin: '1234');

        Livewire::actingAs($this->makeOperator())
            ->test(DiscountModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-discount-open', shop_id: (int) $shop->id, scope: 'item', target_id: (int) $line->id)
            ->set('approverStaffId', (int) $approver->id)
            ->set('approverPin', '0000')
            ->set('reason', 'bad')
            ->set('flatMinor', 500)
            ->call('submit')
            ->assertSet('uiState', 'failed')
            ->assertSet('open', true);

        $this->assertDatabaseCount('discount_audit_logs', 0);
    }

    public function test_approver_options_exclude_below_level_3(): void
    {
        $shop = $this->makeShop('dm-approvers');
        $this->makeApprover($shop, level: 2, name: 'junior');
        $this->makeApprover($shop, level: 3, name: 'manager');
        $this->makeApprover($shop, level: 4, name: 'boss');

        $component = Livewire::actingAs($this->makeOperator())
            ->test(DiscountModal::class, ['shopId' => (int) $shop->id]);

        $options = $component->get('approverOptions');
        $names = array_map(static fn (array $r): string => $r['name'], $options);
        $this->assertNotContains('junior', $names);
        $this->assertContains('manager', $names);
        $this->assertContains('boss', $names);
    }

    public function test_other_shop_event_is_ignored(): void
    {
        $shop = $this->makeShop('dm-other');
        $session = $this->openActiveSession($shop, $this->makeCustomerTable($shop));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 1_000);

        Livewire::test(DiscountModal::class, ['shopId' => (int) $shop->id])
            ->dispatch('pos-discount-open', shop_id: 42_424, scope: 'item', target_id: (int) $line->id)
            ->assertSet('open', false);
    }
}
