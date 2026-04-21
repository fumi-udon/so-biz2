<?php

namespace Tests\Feature\Livewire\Pos;

use App\Domains\Pos\Tables\TableCategory;
use App\Domains\Pos\Tables\TableUiStatus;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Livewire\Pos\StaffMealBar;
use App\Livewire\Pos\TableStatusGrid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

/**
 * Phase 2.5: TableStatusGrid(Livewire) が
 *   - TableDashboardQueryService から来た `tiles` を
 *   - TableCategory ごとの 3 セクションに分け
 *   - TableUiStatus → Tailwind クラスへの純粋マッピングで描画する
 * ことを、実際の Livewire render を通して証明する。
 */
class TableStatusGridTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_component_mounts_and_loads_customer_tiles_even_when_all_free(): void
    {
        $shop = $this->makeShop('lw-free');
        $this->makeCustomerTable($shop, 10);
        $this->makeCustomerTable($shop, 11);

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $component->assertOk();
        $tiles = $component->instance()->tiles;
        $this->assertCount(2, $tiles);

        $grouped = $component->instance()->getGroupedTilesProperty();
        $this->assertCount(2, $grouped['customer']);
        $this->assertCount(0, $grouped['takeaway']);
        $this->assertCount(0, $grouped['staff']);
    }

    public function test_takeaway_tiles_suppressed_while_takeout_ui_frozen(): void
    {
        $shop = $this->makeShop('lw-takeaway');
        $this->makeCustomerTable($shop, 10);
        $idleTakeaway = $this->makeTakeawayTable($shop, 200);
        $busyTakeaway = $this->makeTakeawayTable($shop, 201);
        $busySession = $this->openActiveSession($shop, $busyTakeaway);
        $this->placeOrderAt($shop, $busySession, OrderStatus::Placed, Carbon::parse('2026-01-15 12:00:00'));

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $grouped = $component->instance()->getGroupedTilesProperty();
        $this->assertCount(0, $grouped['takeaway']);
        $component->assertDontSeeHtml('data-category="takeaway"');
        $this->assertContains($idleTakeaway->id, array_column($component->instance()->tiles, 'restaurantTableId'));
    }

    public function test_staff_tiles_are_always_visible_including_free(): void
    {
        $shop = $this->makeShop('lw-staff');
        $this->makeCustomerTable($shop, 10);
        $this->makeStaffTable($shop, 100);
        $busyStaff = $this->makeStaffTable($shop, 101);
        $busySession = $this->openActiveSession($shop, $busyStaff);
        $this->placeOrderAt($shop, $busySession, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $grouped = $component->instance()->getGroupedTilesProperty();
        $this->assertCount(2, $grouped['staff']);
        $this->assertSame([100, 101], array_column($grouped['staff'], 'restaurantTableId'));

        $bar = Livewire::test(StaffMealBar::class, [
            'shopId' => $shop->id,
            'staffDoorOpen' => true,
        ]);
        $bar->assertSeeHtml('data-category="staff"');
    }

    public function test_alert_status_surfaces_red_600_and_animate_pulse_classes_in_html(): void
    {
        $shop = $this->makeShop('lw-alert-html');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->markAdditionPrintedAt($session, $printAt);
        $this->placeOrderAt($shop, $session, OrderStatus::Placed, $printAt->copy()->addSecond());

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $component->assertOk();
        $component->assertSeeHtml('data-ui-status="alert"');
        $component->assertSeeHtml('bg-red-600');
        $component->assertSeeHtml('animate-pulse');
    }

    public function test_billed_status_surfaces_lemon_palette_without_pulse(): void
    {
        $shop = $this->makeShop('lw-billed-html');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $this->markAdditionPrintedAt($session, Carbon::parse('2026-01-15 12:30:00'));

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $component->assertOk();
        $component->assertSeeHtml('data-ui-status="billed"');
        $component->assertSeeHtml('bg-yellow-300');
        $component->assertDontSeeHtml('animate-pulse');
    }

    public function test_pending_status_surfaces_mario_red_palette(): void
    {
        $shop = $this->makeShop('lw-pending-html');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->placeOrderAt($shop, $session, OrderStatus::Placed, Carbon::parse('2026-01-15 12:00:00'));

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $component->assertOk();
        $component->assertSeeHtml('data-ui-status="pending"');
        $component->assertSeeHtml('bg-red-600');
    }

    public function test_free_status_renders_white_background(): void
    {
        $shop = $this->makeShop('lw-free-html');
        $this->makeCustomerTable($shop, 10);

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);

        $component->assertOk();
        $component->assertSeeHtml('data-ui-status="free"');
        $component->assertSeeHtml('bg-white');
    }

    public function test_refresh_tiles_event_picks_up_new_alert_after_addition_print(): void
    {
        $shop = $this->makeShop('lw-refresh');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $this->markAdditionPrintedAt($session, Carbon::parse('2026-01-15 12:30:00'));

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);
        $this->assertSame(TableUiStatus::Billed->value, $component->instance()->tiles[0]['uiStatus']);

        $this->placeOrderAt($shop, $session, OrderStatus::Placed, Carbon::parse('2026-01-15 12:30:05'));

        $component->dispatch('pos-refresh-tiles');

        $this->assertSame(TableUiStatus::Alert->value, $component->instance()->tiles[0]['uiStatus']);
        $component->assertSeeHtml('data-ui-status="alert"');
        $component->assertSeeHtml('animate-pulse');
    }

    public function test_alert_returns_to_active_after_new_orders_are_validated(): void
    {
        $shop = $this->makeShop('lw-alert-back-to-active');
        $table = $this->makeCustomerTable($shop, 10);
        $session = $this->openActiveSession($shop, $table);
        $baseOrder = $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $baseLine = $this->addLineAt($shop, $baseOrder, Carbon::parse('2026-01-15 12:00:01'));
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->markAdditionPrintedAt($session, $printAt);
        $newOrder = $this->placeOrderAt($shop, $session, OrderStatus::Placed, $printAt->copy()->addSecond());
        $newLine = $this->addLineAt($shop, $newOrder, $printAt->copy()->addSeconds(2));

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);
        $this->assertSame(TableUiStatus::Alert->value, $component->instance()->tiles[0]['uiStatus']);

        $newOrder->status = OrderStatus::Confirmed;
        $newOrder->save();
        $newLine->status = OrderLineStatus::Confirmed;
        $newLine->save();
        $baseLine->status = OrderLineStatus::Confirmed;
        $baseLine->save();

        $component->dispatch('pos-refresh-tiles');
        $this->assertSame(TableUiStatus::Active->value, $component->instance()->tiles[0]['uiStatus']);
        $component->assertSeeHtml('data-ui-status="active"');
        $component->assertSeeHtml('bg-blue-50');
    }

    public function test_grid_omits_tiles_for_tables_outside_of_bucket_ranges(): void
    {
        $shop = $this->makeShop('lw-out-of-range');
        $this->makeCustomerTable($shop, 10);
        \DB::table('restaurant_tables')->insert([
            'id' => 7777,
            'shop_id' => $shop->id,
            'name' => 'Legacy',
            'qr_token' => 'legacy-'.bin2hex(random_bytes(6)),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);
        $grouped = $component->instance()->getGroupedTilesProperty();

        $customerIds = array_column($grouped['customer'], 'restaurantTableId');
        $this->assertContains(10, $customerIds);
        $this->assertNotContains(7777, $customerIds);
        $this->assertNotContains(7777, array_column($grouped['takeaway'], 'restaurantTableId'));
        $this->assertNotContains(7777, array_column($grouped['staff'], 'restaurantTableId'));

        // カテゴリ不明 tile のカテゴリは null になることも assert。
        $nullCategoryTiles = array_values(array_filter(
            $component->instance()->tiles,
            static fn (array $t) => $t['category'] === null,
        ));
        $this->assertCount(1, $nullCategoryTiles);
        $this->assertSame(7777, $nullCategoryTiles[0]['restaurantTableId']);
    }

    public function test_ghost_staff_block_reflects_staff_door_open_prop(): void
    {
        $shop = $this->makeShop('lw-staff-door-prop');
        $this->makeStaffTable($shop, 100);

        $whenClosed = Livewire::test(StaffMealBar::class, [
            'shopId' => $shop->id,
            'staffDoorOpen' => false,
        ]);
        $whenClosed->assertOk();
        $whenClosed->assertSeeHtml('aria-expanded="false"');
        $whenClosed->assertSeeHtml('aria-hidden="true"');

        $whenOpen = Livewire::test(StaffMealBar::class, [
            'shopId' => $shop->id,
            'staffDoorOpen' => true,
        ]);
        $whenOpen->assertOk();
        $whenOpen->assertSeeHtml('aria-expanded="true"');
        $whenOpen->assertSeeHtml('aria-hidden="false"');
        $whenOpen->assertSeeHtml('data-category="staff"');
    }

    public function test_customer_and_staff_buckets_render_takeaway_suppressed(): void
    {
        $shop = $this->makeShop('lw-three-buckets');
        $c = $this->makeCustomerTable($shop, 12);
        $t = $this->makeTakeawayTable($shop, 210);
        $s = $this->makeStaffTable($shop, 102);

        foreach ([$c, $t, $s] as $table) {
            $session = $this->openActiveSession($shop, $table);
            $this->placeOrderAt($shop, $session, OrderStatus::Placed, Carbon::parse('2026-01-15 12:00:00'));
        }

        $component = Livewire::test(TableStatusGrid::class, ['shopId' => $shop->id]);
        $grouped = $component->instance()->getGroupedTilesProperty();

        $this->assertSame([12], array_column($grouped['customer'], 'restaurantTableId'));
        $this->assertSame([], array_column($grouped['takeaway'], 'restaurantTableId'));
        $this->assertSame([102], array_column($grouped['staff'], 'restaurantTableId'));
        foreach (['customer', 'staff'] as $group) {
            $this->assertSame(TableUiStatus::Pending->value, $grouped[$group][0]['uiStatus']);
        }

        $component->assertSeeHtml('data-category="'.TableCategory::Customer->value.'"');
        $component->assertDontSeeHtml('data-category="'.TableCategory::Takeaway->value.'"');

        $staffBar = Livewire::test(StaffMealBar::class, [
            'shopId' => $shop->id,
            'staffDoorOpen' => true,
        ]);
        $staffBar->assertSeeHtml('data-category="'.TableCategory::Staff->value.'"');
    }
}
