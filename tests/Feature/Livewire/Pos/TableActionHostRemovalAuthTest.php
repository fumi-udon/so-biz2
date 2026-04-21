<?php

namespace Tests\Feature\Livewire\Pos;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Pos\TableActionHost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

class TableActionHostRemovalAuthTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    public function test_printed_session_requires_pin_then_deletes_and_writes_audit_log(): void
    {
        $shop = $this->makeShop('remove-pin');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->markAdditionPrintedAt($session, Carbon::parse('2026-01-15 12:30:00'));
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 1200, OrderStatus::Confirmed);
        $approver = $this->makeApprover($shop, 3, '1234');
        $operator = $this->makeOperator('remove-pin');
        $this->actingAs($operator);

        Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->call('promptRemoveLine', (int) $line->id)
            ->assertSet('removeAuthPanelOpen', true)
            ->set('removeApproverStaffId', (int) $approver->id)
            ->set('removeApproverPin', '1234')
            ->call('confirmRemoveWithAuth');

        $this->assertDatabaseMissing('order_lines', ['id' => (int) $line->id]);
        $this->assertDatabaseHas('pos_line_deletion_audit_logs', [
            'shop_id' => $shop->id,
            'order_line_id' => (int) $line->id,
            'approver_staff_id' => (int) $approver->id,
            'approval_mode' => 'pin',
            'was_printed' => true,
            'removed_by_user_id' => (int) $operator->id,
        ]);
    }

    public function test_printed_session_second_delete_becomes_open_after_printed_marker_rollback(): void
    {
        $shop = $this->makeShop('remove-ttl');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->markAdditionPrintedAt($session, Carbon::parse('2026-01-15 12:30:00'));
        ['line' => $firstLine] = $this->placeLinedOrder($shop, $session, 1300, OrderStatus::Confirmed);
        ['line' => $secondLine] = $this->placeLinedOrder($shop, $session, 1400, OrderStatus::Confirmed);
        $approver = $this->makeApprover($shop, 3, '1234');

        $component = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->call('promptRemoveLine', (int) $firstLine->id)
            ->set('removeApproverStaffId', (int) $approver->id)
            ->set('removeApproverPin', '1234')
            ->call('confirmRemoveWithAuth');

        $component->call('promptRemoveLine', (int) $secondLine->id)
            ->assertSet('removeAuthPanelOpen', false);

        $this->assertDatabaseMissing('order_lines', ['id' => (int) $secondLine->id]);
        $this->assertDatabaseHas('pos_line_deletion_audit_logs', [
            'shop_id' => $shop->id,
            'order_line_id' => (int) $secondLine->id,
            'approver_staff_id' => null,
            'approval_mode' => 'open',
            'was_printed' => false,
        ]);
    }

    public function test_ttl_cache_does_not_cross_table_session_boundary(): void
    {
        $shop = $this->makeShop('remove-ttl-boundary');
        $tableA = $this->makeCustomerTable($shop, 10);
        $tableB = $this->makeCustomerTable($shop, 11);
        $sessionA = $this->openActiveSession($shop, $tableA);
        $sessionB = $this->openActiveSession($shop, $tableB);
        $this->markAdditionPrintedAt($sessionA, Carbon::parse('2026-01-15 12:30:00'));
        $this->markAdditionPrintedAt($sessionB, Carbon::parse('2026-01-15 12:31:00'));
        ['line' => $lineA] = $this->placeLinedOrder($shop, $sessionA, 1200, OrderStatus::Confirmed);
        ['line' => $lineB] = $this->placeLinedOrder($shop, $sessionB, 1250, OrderStatus::Confirmed);
        $approver = $this->makeApprover($shop, 3, '1234');

        $component = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $tableA->id, $sessionA->id)
            ->call('promptRemoveLine', (int) $lineA->id)
            ->set('removeApproverStaffId', (int) $approver->id)
            ->set('removeApproverPin', '1234')
            ->call('confirmRemoveWithAuth');

        $this->assertDatabaseMissing('order_lines', ['id' => (int) $lineA->id]);

        $component->call('onActionHostOpened', $tableB->id, $sessionB->id)
            ->call('promptRemoveLine', (int) $lineB->id)
            ->assertSet('removeAuthPanelOpen', true);
    }

    public function test_preprint_session_allows_delete_without_pin_and_logs_open_mode(): void
    {
        $shop = $this->makeShop('remove-open');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 1100, OrderStatus::Confirmed);

        Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->call('promptRemoveLine', (int) $line->id);

        $this->assertDatabaseMissing('order_lines', ['id' => (int) $line->id]);
        $this->assertDatabaseHas('pos_line_deletion_audit_logs', [
            'shop_id' => $shop->id,
            'order_line_id' => (int) $line->id,
            'approver_staff_id' => null,
            'approval_mode' => 'open',
            'was_printed' => false,
        ]);
    }

    public function test_deleting_last_line_closes_table_session_immediately(): void
    {
        $shop = $this->makeShop('remove-close');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        ['line' => $line] = $this->placeLinedOrder($shop, $session, 900, OrderStatus::Confirmed);

        Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->call('promptRemoveLine', (int) $line->id);

        $this->assertDatabaseHas('table_sessions', [
            'id' => (int) $session->id,
            'status' => TableSessionStatus::Closed->value,
        ]);
    }

    public function test_printed_session_delete_with_remaining_lines_resets_printed_marker_to_active(): void
    {
        $shop = $this->makeShop('remove-rollback');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->markAdditionPrintedAt($session, Carbon::parse('2026-01-15 12:30:00'));
        ['line' => $firstLine] = $this->placeLinedOrder($shop, $session, 1000, OrderStatus::Confirmed);
        ['line' => $secondLine] = $this->placeLinedOrder($shop, $session, 1100, OrderStatus::Confirmed);
        $approver = $this->makeApprover($shop, 3, '1234');

        Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', $table->id, $session->id)
            ->call('promptRemoveLine', (int) $firstLine->id)
            ->set('removeApproverStaffId', (int) $approver->id)
            ->set('removeApproverPin', '1234')
            ->call('confirmRemoveWithAuth');

        $this->assertDatabaseMissing('order_lines', ['id' => (int) $firstLine->id]);
        $this->assertDatabaseHas('order_lines', ['id' => (int) $secondLine->id]);
        $this->assertDatabaseHas('table_sessions', [
            'id' => (int) $session->id,
            'status' => TableSessionStatus::Active->value,
            'last_addition_printed_at' => null,
        ]);
    }
}
