<?php

namespace Tests\Feature\Kds;

use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Services\Kds\KdsQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

class KdsQueryServicePullActiveSessionTicketsTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    private function makeShopWithTableItem(): array
    {
        $shop = Shop::query()->create([
            'name' => 'KDS Pull Shop',
            'slug' => 'kds-pull-'.bin2hex(random_bytes(3)),
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
            'name' => 'Item',
            'kitchen_name' => 'Item K',
            'slug' => 'item-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T-Pull',
            'qr_token' => 'kds-pull-t-'.bin2hex(random_bytes(6)),
            'sort_order' => 0,
            'is_active' => true,
        ]);

        return compact('shop', 'item', 'table');
    }

    public function test_served_line_remains_visible_while_virtual_batch_has_pending_lines(): void
    {
        $p = $this->makeShopWithTableItem();
        $session = $this->createActiveTableSession($p['shop'], $p['table']);
        $order = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 2000,
            'placed_at' => now(),
        ]);
        $batchUuid = '70000000-0000-4000-8000-000000000001';
        $served = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'A',
            'snapshot_kitchen_name' => 'A',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
            'kds_ticket_batch_id' => $batchUuid,
        ]);
        $pending = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'B',
            'snapshot_kitchen_name' => 'B',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'kds_ticket_batch_id' => $batchUuid,
        ]);

        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard((int) $p['shop']->id);
        $ids = $rows->pluck('id')->all();

        $this->assertContains($served->id, $ids);
        $this->assertContains($pending->id, $ids);
    }

    public function test_served_lines_excluded_when_sibling_virtual_batch_has_pending(): void
    {
        $p = $this->makeShopWithTableItem();
        $session = $this->createActiveTableSession($p['shop'], $p['table']);
        $orderA = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $orderB = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $lineServedA = OrderLine::query()->create([
            'order_id' => $orderA->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'A',
            'snapshot_kitchen_name' => 'A',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
            'kds_ticket_batch_id' => '80000000-0000-4000-8000-000000000001',
        ]);
        OrderLine::query()->create([
            'order_id' => $orderB->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'B',
            'snapshot_kitchen_name' => 'B',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'kds_ticket_batch_id' => '80000000-0000-4000-8000-000000000002',
        ]);

        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard((int) $p['shop']->id);
        $ids = $rows->pluck('id')->all();

        $this->assertNotContains($lineServedA->id, $ids);
        $bLineId = (int) OrderLine::query()->where('order_id', $orderB->id)->where('status', OrderLineStatus::Confirmed)->value('id');
        $this->assertContains($bLineId, $ids);
    }

    public function test_session_disappears_when_all_lines_served(): void
    {
        $p = $this->makeShopWithTableItem();
        $session = $this->createActiveTableSession($p['shop'], $p['table']);
        $order = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 2000,
            'placed_at' => now(),
        ]);
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'A',
            'snapshot_kitchen_name' => 'A',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
        ]);
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'B',
            'snapshot_kitchen_name' => 'B',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Served,
            'line_revision' => 2,
        ]);

        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard((int) $p['shop']->id);

        $this->assertCount(0, $rows);
    }

    public function test_cancelled_lines_never_returned(): void
    {
        $p = $this->makeShopWithTableItem();
        $session = $this->createActiveTableSession($p['shop'], $p['table']);
        $order = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 3000,
            'placed_at' => now(),
        ]);
        $cancelled = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'X',
            'snapshot_kitchen_name' => 'X',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Cancelled,
            'line_revision' => 1,
        ]);
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Y',
            'snapshot_kitchen_name' => 'Y',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard((int) $p['shop']->id);

        $this->assertFalse($rows->pluck('id')->contains($cancelled->id));
        $this->assertTrue($rows->every(static fn (OrderLine $l): bool => $l->status !== OrderLineStatus::Cancelled));
    }

    public function test_inactive_session_not_returned(): void
    {
        $p = $this->makeShopWithTableItem();
        $session = $this->createActiveTableSession($p['shop'], $p['table']);
        $session->update([
            'status' => TableSessionStatus::Closed,
            'closed_at' => now(),
        ]);
        $order = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Z',
            'snapshot_kitchen_name' => 'Z',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard((int) $p['shop']->id);

        $this->assertCount(0, $rows);
    }

    public function test_multi_shop_isolation(): void
    {
        $p1 = $this->makeShopWithTableItem();
        $p2 = $this->makeShopWithTableItem();
        $s1 = $this->createActiveTableSession($p1['shop'], $p1['table']);
        $s2 = $this->createActiveTableSession($p2['shop'], $p2['table']);
        $o1 = PosOrder::query()->create([
            'shop_id' => $p1['shop']->id,
            'table_session_id' => $s1->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $o2 = PosOrder::query()->create([
            'shop_id' => $p2['shop']->id,
            'table_session_id' => $s2->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        $line1 = OrderLine::query()->create([
            'order_id' => $o1->id,
            'menu_item_id' => $p1['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'S1',
            'snapshot_kitchen_name' => 'S1',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);
        $line2 = OrderLine::query()->create([
            'order_id' => $o2->id,
            'menu_item_id' => $p2['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'S2',
            'snapshot_kitchen_name' => 'S2',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
        ]);

        $rows = app(KdsQueryService::class)->pullActiveSessionTicketsForDashboard((int) $p1['shop']->id);

        $this->assertTrue($rows->pluck('id')->contains($line1->id));
        $this->assertFalse($rows->pluck('id')->contains($line2->id));
    }
}
