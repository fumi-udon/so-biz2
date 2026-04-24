<?php

namespace Tests\Feature\Kds;

use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Kds\KdsDashboard;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

class KdsDashboardTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    /**
     * @return array{shop: Shop, table: RestaurantTable, item: MenuItem, line: OrderLine}
     */
    private function seedActiveTicket(string $tableName = 'T-1'): array
    {
        $shop = Shop::query()->create([
            'name' => 'KDS UI Shop',
            'slug' => 'kds-ui-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Mains',
            'slug' => 'mains-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'Ramen',
            'kitchen_name' => 'Ramen K',
            'slug' => 'ramen-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => $tableName,
            'qr_token' => 'kds-ui-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $session = $this->createActiveTableSession($shop, $table);

        $order = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $line = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Ramen',
            'snapshot_kitchen_name' => 'Ramen K',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        return ['shop' => $shop, 'table' => $table, 'item' => $item, 'line' => $line];
    }

    public function test_dashboard_renders_table_columns_with_active_tickets(): void
    {
        $p = $this->seedActiveTicket('T-A');

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertSee('Ramen K')
            ->assertSee('T-A');
    }

    public function test_tap_marks_line_served_and_increments_revision(): void
    {
        $p = $this->seedActiveTicket('T-B');

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->call('markServed', (int) $p['line']->id, 1)
            ->assertHasNoErrors();

        $fresh = OrderLine::query()->whereKey($p['line']->id)->sole();
        $this->assertSame(OrderLineStatus::Served, $fresh->status);
        $this->assertSame(2, (int) $fresh->line_revision);
    }

    public function test_revision_conflict_is_swallowed_and_does_not_500(): void
    {
        $p = $this->seedActiveTicket('T-C');

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->call('markServed', (int) $p['line']->id, 999)
            ->assertHasNoErrors()
            ->assertOk();

        $fresh = OrderLine::query()->whereKey($p['line']->id)->sole();
        $this->assertSame(OrderLineStatus::Confirmed, $fresh->status);
        $this->assertSame(1, (int) $fresh->line_revision);
    }

    public function test_fully_served_session_hidden_from_dashboard(): void
    {
        $p = $this->seedActiveTicket('T-ALL-SERVED');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
            'updated_at' => now(),
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertDontSee('T-ALL-SERVED');
    }

    public function test_column_with_mixed_pending_and_served_stays_visible(): void
    {
        $p = $this->seedActiveTicket('T-MIX');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
        ]);
        OrderLine::query()->create([
            'order_id' => (int) $p['line']->order_id,
            'menu_item_id' => (int) $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Ramen',
            'snapshot_kitchen_name' => 'Ramen K',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertSee('T-MIX')
            ->assertSee('Ramen K');
    }

    public function test_old_served_lines_remain_visible_when_addon_confirmed_same_session(): void
    {
        $p = $this->seedActiveTicket('T-DELTA');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
            'snapshot_kitchen_name' => 'Old served item',
            'updated_at' => now()->subMinutes(2),
        ]);
        OrderLine::query()->create([
            'order_id' => (int) $p['line']->order_id,
            'menu_item_id' => (int) $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Fresh add-on',
            'snapshot_kitchen_name' => 'Fresh add-on',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertSee('Fresh add-on')
            ->assertSee('Old served item');
    }

    public function test_history_columns_empty_when_drawer_closed(): void
    {
        $p = $this->seedActiveTicket('T-H0');

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        $c = Livewire::test(KdsDashboard::class)
            ->assertSet('historyOpen', false);

        $this->assertSame([], $c->instance()->historyColumns);
    }

    public function test_history_drawer_shows_empty_message_when_no_served_today(): void
    {
        $p = $this->seedActiveTicket('T-H-EMPTY');

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->set('historyOpen', true)
            ->assertSee(__('kds.history_empty'));
    }

    public function test_history_drawer_lists_served_line_and_revert_works(): void
    {
        $p = $this->seedActiveTicket('T-HIST');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->set('historyOpen', true)
            ->assertSee(__('kds.history_title'))
            ->assertSee('Ramen K')
            ->call('revertToConfirmed', (int) $p['line']->id, 2)
            ->assertHasNoErrors();

        $fresh = OrderLine::query()->whereKey($p['line']->id)->sole();
        $this->assertSame(OrderLineStatus::Confirmed, $fresh->status);
        $this->assertSame(3, (int) $fresh->line_revision);
    }

    public function test_history_shows_closed_badge_for_billed_session(): void
    {
        $p = $this->seedActiveTicket('T-CLOSED');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
        ]);
        $orderId = (int) OrderLine::query()->whereKey($p['line']->id)->value('order_id');
        $tableSessionId = (int) PosOrder::query()->whereKey($orderId)->value('table_session_id');
        TableSession::query()->whereKey($tableSessionId)->update([
            'status' => TableSessionStatus::Closed,
            'closed_at' => now(),
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->set('historyOpen', true)
            ->assertSee(__('kds.history_closed_badge'))
            ->assertSee('Ramen K');
    }

    public function test_dashboard_renders_selected_style_and_topping_names_for_each_ticket(): void
    {
        $p = $this->seedActiveTicket('T-OPT');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'snapshot_options_payload' => [
                'style' => [
                    'id' => 'tonkotsu',
                    'name' => 'Tonkotsu',
                    'price_minor' => 1500,
                ],
                'toppings' => [
                    ['id' => 'nori', 'name' => 'Nori', 'price_delta_minor' => 100],
                    ['id' => 'ajitama', 'name' => 'Ajitama', 'price_delta_minor' => 200],
                ],
                'note' => '',
                'client' => ['lineId' => null, 'mergeKey' => null],
            ],
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertSee('Ramen K')
            ->assertSee('[Tonkotsu]', false)
            ->assertSee('+ Nori, Ajitama');
    }

    public function test_dashboard_omits_pending_and_served_status_word_labels(): void
    {
        $pending = $this->seedActiveTicket('T-P');
        $servedSeed = $this->seedActiveTicket('T-S');
        OrderLine::query()->whereKey($servedSeed['line']->id)->update([
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
        ]);
        OrderLine::query()->create([
            'order_id' => (int) $servedSeed['line']->order_id,
            'menu_item_id' => (int) $servedSeed['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Hold open',
            'snapshot_kitchen_name' => 'Hold open K',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        // Match only visible text content (between tags), so substrings inside
        // attribute values like wire:target="markServed,..." are not flagged.
        $pendingLabelMarkup = '>'.__('kds.status_pending').'<';
        $servedLabelMarkup = '>'.__('kds.status_served').'<';

        $this->withSession(['kds.active_shop_id' => (int) $pending['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertDontSee($pendingLabelMarkup, false)
            ->assertSee('T-P');

        $this->withSession(['kds.active_shop_id' => (int) $servedSeed['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertDontSee($servedLabelMarkup, false)
            ->assertSee('T-S');
    }

    public function test_kds_column_sort_orders_by_batch_id_then_session_then_order(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS sort shop',
            'slug' => 'kds-sort-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Sort',
            'slug' => 'sort-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'Sort item',
            'kitchen_name' => 'Sort item',
            'slug' => 'sort-item-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        DB::table('restaurant_tables')->insert([
            [
                'id' => 10,
                'shop_id' => $shop->id,
                'name' => 'C-10',
                'qr_token' => 'kds-sort-customer-'.bin2hex(random_bytes(4)),
                'sort_order' => 10,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 100,
                'shop_id' => $shop->id,
                'name' => 'S-100',
                'qr_token' => 'kds-sort-staff-'.bin2hex(random_bytes(4)),
                'sort_order' => 100,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 200,
                'shop_id' => $shop->id,
                'name' => 'T-200',
                'qr_token' => 'kds-sort-takeaway-'.bin2hex(random_bytes(4)),
                'sort_order' => 200,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $customerTable = RestaurantTable::query()->whereKey(10)->sole();
        $staffTable = RestaurantTable::query()->whereKey(100)->sole();
        $takeawayTable = RestaurantTable::query()->whereKey(200)->sole();
        $customerSession = $this->createActiveTableSession($shop, $customerTable);
        $staffSession = $this->createActiveTableSession($shop, $staffTable);
        $takeawaySession = $this->createActiveTableSession($shop, $takeawayTable);

        $customerOrder = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $customerSession->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $staffOrder = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $staffSession->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $takeawayOrder = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $takeawaySession->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);

        OrderLine::query()->create([
            'order_id' => $customerOrder->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'C line',
            'snapshot_kitchen_name' => 'C line',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'kds_ticket_batch_id' => '10000000-0000-4000-8000-000000000001',
        ]);
        OrderLine::query()->create([
            'order_id' => $staffOrder->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'S line',
            'snapshot_kitchen_name' => 'S line',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'kds_ticket_batch_id' => '10000000-0000-4000-8000-000000000002',
        ]);
        OrderLine::query()->create([
            'order_id' => $takeawayOrder->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'T line',
            'snapshot_kitchen_name' => 'T line',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'kds_ticket_batch_id' => '10000000-0000-4000-8000-000000000003',
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $component = Livewire::test(KdsDashboard::class);
        $columns = $component->instance()->tableColumns;
        $ids = array_map(static fn (array $c): int => (int) $c['tableId'], $columns);

        $this->assertSame(10, $ids[0]);
        $this->assertSame(100, $ids[1]);
        $this->assertSame(200, $ids[2]);
    }

    public function test_realtime_state_switches_poll_interval_between_60_and_2_seconds(): void
    {
        $seed = $this->seedActiveTicket('T-STATE');
        $this->withSession(['kds.active_shop_id' => (int) $seed['shop']->id]);

        Livewire::test(KdsDashboard::class)
            ->assertSet('pollSeconds', 2)
            ->call('syncRealtimeState', 'connected')
            ->assertSet('pollSeconds', 60)
            ->call('syncRealtimeState', 'disconnected')
            ->assertSet('pollSeconds', 2);
    }

    public function test_single_validate_merges_multiple_placed_orders_into_one_kds_column(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS batch merge',
            'slug' => 'kds-batch-merge-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'A',
            'kitchen_name' => 'A',
            'slug' => 'a-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T-MERGE',
            'qr_token' => 'kds-merge-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $session = $this->createActiveTableSession($shop, $table);

        $o1 = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Placed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $l1 = OrderLine::query()->create([
            'order_id' => $o1->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'L1',
            'snapshot_kitchen_name' => 'L1',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Placed,
            'line_revision' => 1,
        ]);
        $o2 = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Placed,
            'total_price_minor' => 2000,
            'placed_at' => now(),
        ]);
        $l2 = OrderLine::query()->create([
            'order_id' => $o2->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 2000,
            'line_total_minor' => 2000,
            'snapshot_name' => 'L2',
            'snapshot_kitchen_name' => 'L2',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Placed,
            'line_revision' => 1,
        ]);

        $session->refresh();
        $n = app(RecuPlacedOrdersForSessionAction::class)->execute(
            (int) $shop->id,
            (int) $session->id,
            (int) $session->session_revision
        );
        $this->assertSame(2, $n);

        $id1 = (string) OrderLine::query()->whereKey($l1->id)->value('kds_ticket_batch_id');
        $id2 = (string) OrderLine::query()->whereKey($l2->id)->value('kds_ticket_batch_id');
        $this->assertNotSame('', $id1);
        $this->assertSame($id1, $id2);

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $c = Livewire::test(KdsDashboard::class);
        $columns = $c->instance()->tableColumns;
        $this->assertCount(1, $columns, 'KDS 列は同一バッチ＝1列（複数 order_id でも分離しない）');
        $this->assertCount(2, $columns[0]['tickets']);
    }

    public function test_same_table_second_send_renders_as_separate_add_batch_column(): void
    {
        $p = $this->seedActiveTicket('T-BATCH');
        OrderLine::query()->whereKey($p['line']->id)->update([
            'snapshot_kitchen_name' => 'Batch One',
            'status' => OrderLineStatus::Confirmed,
        ]);

        $secondOrder = PosOrder::query()->create([
            'shop_id' => (int) $p['shop']->id,
            'table_session_id' => (int) $p['line']->order->table_session_id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1200,
            'placed_at' => now()->addMinute(),
        ]);
        OrderLine::query()->create([
            'order_id' => (int) $secondOrder->id,
            'menu_item_id' => (int) $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1200,
            'line_total_minor' => 1200,
            'snapshot_name' => 'Batch Two',
            'snapshot_kitchen_name' => 'Batch Two',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        Livewire::test(KdsDashboard::class)
            ->assertSee('T-BATCH')
            ->assertSee('T-BATCH (Add #2)')
            ->assertSee('Batch One')
            ->assertSee('Batch Two');
    }

    public function test_mark_served_does_not_reorder_line_positions_inside_ticket(): void
    {
        $p = $this->seedActiveTicket('T-STABLE');
        $orderId = (int) $p['line']->order_id;
        $firstId = (int) $p['line']->id;
        $second = OrderLine::query()->create([
            'order_id' => $orderId,
            'menu_item_id' => (int) $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Second',
            'snapshot_kitchen_name' => 'Second',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $p['shop']->id]);
        $c = Livewire::test(KdsDashboard::class);
        $before = $c->instance()->tableColumns;
        $beforeIds = array_map(
            static fn (OrderLine $line): int => (int) $line->id,
            $before[0]['tickets']
        );

        $c->call('markServed', $firstId, 1);
        $after = $c->instance()->tableColumns;
        $afterIds = array_map(
            static fn (OrderLine $line): int => (int) $line->id,
            $after[0]['tickets']
        );

        $this->assertSame($beforeIds, $afterIds);
        $this->assertContains((int) $second->id, $afterIds);
    }

    public function test_ticket_lines_are_sorted_by_category_then_item_sort_then_name(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS sort tuple shop',
            'slug' => 'kds-sort-tuple-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T-SORT-TUPLE',
            'qr_token' => 'kds-sort-tuple-'.bin2hex(random_bytes(8)),
            'sort_order' => 10,
            'is_active' => true,
        ]);
        $session = $this->createActiveTableSession($shop, $table);
        $order = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 3000,
            'placed_at' => now(),
        ]);

        $catA = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'A',
            'slug' => 'a-'.bin2hex(random_bytes(3)),
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $catB = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'B',
            'slug' => 'b-'.bin2hex(random_bytes(3)),
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $itemLateCategory = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $catB->id,
            'name' => 'Zebra',
            'kitchen_name' => 'Zebra',
            'slug' => 'zebra-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $itemSameCategoryHigherSort = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $catA->id,
            'name' => 'Beta',
            'kitchen_name' => 'Beta',
            'slug' => 'beta-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $itemSameCategoryLowerSort = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $catA->id,
            'name' => 'Alpha',
            'kitchen_name' => 'Alpha',
            'slug' => 'alpha-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $itemLateCategory->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Zebra',
            'snapshot_kitchen_name' => 'Zebra',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $itemSameCategoryHigherSort->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Beta',
            'snapshot_kitchen_name' => 'Beta',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $itemSameCategoryLowerSort->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Alpha',
            'snapshot_kitchen_name' => 'Alpha',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $c = Livewire::test(KdsDashboard::class);
        $columns = $c->instance()->tableColumns;
        $names = array_map(
            static fn (OrderLine $line): string => (string) ($line->snapshot_kitchen_name ?? $line->snapshot_name),
            $columns[0]['tickets']
        );
        $this->assertSame(['Alpha', 'Beta', 'Zebra'], $names);
    }

    public function test_dashboard_shows_at_most_three_columns(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS max cols',
            'slug' => 'kds-max-cols-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-max-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'X',
            'kitchen_name' => 'X',
            'slug' => 'x-max-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 4; $i++) {
            $table = RestaurantTable::query()->create([
                'shop_id' => $shop->id,
                'name' => 'FIFO-'.$i,
                'qr_token' => 'kds-fifo-'.$i.'-'.bin2hex(random_bytes(6)),
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $session = $this->createActiveTableSession($shop, $table);
            $order = PosOrder::query()->create([
                'shop_id' => $shop->id,
                'table_session_id' => $session->id,
                'status' => OrderStatus::Confirmed,
                'total_price_minor' => 1000,
                'placed_at' => now(),
            ]);
            OrderLine::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $item->id,
                'qty' => 1,
                'unit_price_minor' => 1000,
                'line_total_minor' => 1000,
                'snapshot_name' => 'L'.$i,
                'snapshot_kitchen_name' => 'L'.$i,
                'snapshot_options_payload' => [],
                'status' => OrderLineStatus::Confirmed,
                'line_revision' => 1,
                'kds_ticket_batch_id' => sprintf('20000000-0000-4000-8000-%012d', $i),
            ]);
        }

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $c = Livewire::test(KdsDashboard::class);
        $this->assertSame(3, count($c->instance()->tableColumns));
    }

    public function test_dashboard_exposes_queued_count_when_more_than_three(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS queue cnt',
            'slug' => 'kds-queue-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-q-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'X',
            'kitchen_name' => 'X',
            'slug' => 'x-q-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $table = RestaurantTable::query()->create([
                'shop_id' => $shop->id,
                'name' => 'Q-'.$i,
                'qr_token' => 'kds-q-'.$i.'-'.bin2hex(random_bytes(6)),
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $session = $this->createActiveTableSession($shop, $table);
            $order = PosOrder::query()->create([
                'shop_id' => $shop->id,
                'table_session_id' => $session->id,
                'status' => OrderStatus::Confirmed,
                'total_price_minor' => 1000,
                'placed_at' => now(),
            ]);
            OrderLine::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $item->id,
                'qty' => 1,
                'unit_price_minor' => 1000,
                'line_total_minor' => 1000,
                'snapshot_name' => 'L'.$i,
                'snapshot_kitchen_name' => 'L'.$i,
                'snapshot_options_payload' => [],
                'status' => OrderLineStatus::Confirmed,
                'line_revision' => 1,
                'kds_ticket_batch_id' => sprintf('30000000-0000-4000-8000-%012d', $i),
            ]);
        }

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $c = Livewire::test(KdsDashboard::class);
        $this->assertSame(2, $c->instance()->queuedBatchCount);
        $c->assertSee(__('kds.queue_waiting', ['count' => 2]));
    }

    public function test_dashboard_queue_empty_when_three_or_fewer_columns(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS queue zero',
            'slug' => 'kds-q0-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-q0-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'X',
            'kitchen_name' => 'X',
            'slug' => 'x-q0-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            $table = RestaurantTable::query()->create([
                'shop_id' => $shop->id,
                'name' => 'Z-'.$i,
                'qr_token' => 'kds-z-'.$i.'-'.bin2hex(random_bytes(6)),
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $session = $this->createActiveTableSession($shop, $table);
            $order = PosOrder::query()->create([
                'shop_id' => $shop->id,
                'table_session_id' => $session->id,
                'status' => OrderStatus::Confirmed,
                'total_price_minor' => 1000,
                'placed_at' => now(),
            ]);
            OrderLine::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $item->id,
                'qty' => 1,
                'unit_price_minor' => 1000,
                'line_total_minor' => 1000,
                'snapshot_name' => 'L'.$i,
                'snapshot_kitchen_name' => 'L'.$i,
                'snapshot_options_payload' => [],
                'status' => OrderLineStatus::Confirmed,
                'line_revision' => 1,
                'kds_ticket_batch_id' => sprintf('40000000-0000-4000-8000-%012d', $i),
            ]);
        }

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $c = Livewire::test(KdsDashboard::class);
        $this->assertSame(0, $c->instance()->queuedBatchCount);
        $c->assertSee(__('kds.queue_waiting', ['count' => 0]));
    }

    public function test_column_order_preserves_fifo_when_queue_advances(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS fifo advance',
            'slug' => 'kds-fifo-adv-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-fa-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'X',
            'kitchen_name' => 'X',
            'slug' => 'x-fa-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $tables = [];
        $lines = [];
        for ($i = 1; $i <= 4; $i++) {
            $table = RestaurantTable::query()->create([
                'shop_id' => $shop->id,
                'name' => 'ADV-'.$i,
                'qr_token' => 'kds-adv-'.$i.'-'.bin2hex(random_bytes(6)),
                'sort_order' => $i,
                'is_active' => true,
            ]);
            $tables[] = $table;
            $session = $this->createActiveTableSession($shop, $table);
            $order = PosOrder::query()->create([
                'shop_id' => $shop->id,
                'table_session_id' => $session->id,
                'status' => OrderStatus::Confirmed,
                'total_price_minor' => 1000,
                'placed_at' => now(),
            ]);
            $lines[] = OrderLine::query()->create([
                'order_id' => $order->id,
                'menu_item_id' => $item->id,
                'qty' => 1,
                'unit_price_minor' => 1000,
                'line_total_minor' => 1000,
                'snapshot_name' => 'L'.$i,
                'snapshot_kitchen_name' => 'L'.$i,
                'snapshot_options_payload' => [],
                'status' => OrderLineStatus::Confirmed,
                'line_revision' => 1,
                'kds_ticket_batch_id' => sprintf('50000000-0000-4000-8000-%012d', $i),
            ]);
        }

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $c = Livewire::test(KdsDashboard::class);
        $before = $c->instance()->tableColumns;
        $this->assertCount(3, $before);
        $this->assertSame((int) $tables[0]->id, (int) $before[0]['tableId']);

        $c->call('markServed', (int) $lines[0]->id, 1);

        $after = $c->instance()->tableColumns;
        $this->assertCount(3, $after);
        $this->assertSame((int) $tables[1]->id, (int) $after[0]['tableId']);
        $this->assertSame((int) $tables[3]->id, (int) $after[2]['tableId']);
    }
}
