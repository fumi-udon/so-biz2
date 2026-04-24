<?php

declare(strict_types=1);

namespace Tests\Feature\Kds;

use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Livewire\Kds\KdsDashboard;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Support\KdsFilterSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

final class KdsDashboardKdsFilterBootstrapTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    public function test_kds_client_bootstrap_warns_when_filter_not_configured(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS Filter Shop',
            'slug' => 'kds-filter-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $boot = Livewire::test(KdsDashboard::class)->instance()->kdsClientBootstrap;

        $this->assertSame((int) $shop->id, $boot['shopId']);
        $this->assertFalse($boot['filterStrict']);
        $this->assertTrue($boot['showFilterConfigWarning']);
        $this->assertSame([], $boot['kitchenIds']);
        $this->assertSame([], $boot['hallIds']);
    }

    public function test_kds_client_bootstrap_strict_when_any_category_list_configured(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS Filter Shop 2',
            'slug' => 'kds-filter-shop2-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-kfb-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        KdsFilterSetting::saveKitchenCategoryIds((int) $shop->id, [(int) $cat->id]);

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $boot = Livewire::test(KdsDashboard::class)->instance()->kdsClientBootstrap;

        $this->assertTrue($boot['filterStrict']);
        $this->assertFalse($boot['showFilterConfigWarning']);
        $this->assertSame([(int) $cat->id], $boot['kitchenIds']);
    }

    public function test_visible_columns_include_filter_ticket_meta(): void
    {
        $shop = Shop::query()->create([
            'name' => 'KDS Meta Shop',
            'slug' => 'kds-meta-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Cat',
            'slug' => 'c-meta-'.bin2hex(random_bytes(3)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'Item',
            'kitchen_name' => 'Item K',
            'slug' => 'i-meta-'.bin2hex(random_bytes(3)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T-M',
            'qr_token' => 'kds-meta-'.bin2hex(random_bytes(8)),
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
        OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'Item',
            'snapshot_kitchen_name' => 'Item K',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'kds_ticket_batch_id' => '73000000-0000-4000-8000-000000000001',
        ]);

        $this->withSession(['kds.active_shop_id' => (int) $shop->id]);
        $cols = Livewire::test(KdsDashboard::class)->instance()->tableColumns;
        $this->assertCount(1, $cols);
        $this->assertArrayHasKey('filterTicketMeta', $cols[0]);
        $this->assertSame([['c' => (int) $cat->id]], $cols[0]['filterTicketMeta']);

        $metas = Livewire::test(KdsDashboard::class)->instance()->kdsClientBootstrap['columnFilterMetas'];
        $this->assertCount(1, $metas);
        $this->assertSame([['c' => (int) $cat->id]], $metas[0]);
    }
}
