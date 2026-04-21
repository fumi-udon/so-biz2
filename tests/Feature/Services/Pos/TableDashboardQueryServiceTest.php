<?php

namespace Tests\Feature\Services\Pos;

use App\Data\Pos\TableTileAggregate;
use App\Domains\Pos\Tables\TableCategory;
use App\Domains\Pos\Tables\TableUiStatus;
use App\Enums\OrderStatus;
use App\Services\Pos\TableDashboardQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

/**
 * Phase 2 / 2.5: 「BilledからAlertへ誤爆なく・検知漏れなく遷移する」ことを
 * 実 DB（MySQL / Sail のテスト用 DB）で END-TO-END で証明する。
 *
 * ビジネス死活要件:
 *   - レシート発行後に「やっぱりデザート追加」が来ても Alert で即座に赤くなる
 *   - 発行前注文 / 同時刻注文 / voided 注文が Alert を**誤爆**させない
 */
class TableDashboardQueryServiceTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_returns_single_customer_tile_as_free_when_table_has_no_session(): void
    {
        $shop = $this->makeShop('empty');
        $this->makeCustomerTable($shop, 10);

        $tile = $this->firstTile($shop->id);

        $this->assertSame(10, $tile->restaurantTableId);
        $this->assertSame(TableCategory::Customer, $tile->category);
        $this->assertSame(TableUiStatus::Free, $tile->uiStatus);
        $this->assertNull($tile->activeTableSessionId);
        $this->assertNull($tile->lastAdditionPrintedAt);
        $this->assertFalse($tile->hasOrderAfterAdditionPrinted);
    }

    public function test_pending_when_active_session_has_at_least_one_placed_order(): void
    {
        $shop = $this->makeShop('pending');
        $table = $this->makeCustomerTable($shop, 11);
        $session = $this->openActiveSession($shop, $table);
        $this->placeOrderAt($shop, $session, OrderStatus::Placed, Carbon::parse('2026-01-15 12:00:00'));

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Pending, $tile->uiStatus);
    }

    public function test_active_when_all_orders_confirmed_and_no_bill_printed(): void
    {
        $shop = $this->makeShop('active');
        $table = $this->makeCustomerTable($shop, 12);
        $session = $this->openActiveSession($shop, $table);
        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Active, $tile->uiStatus);
    }

    public function test_billed_when_print_timestamp_is_after_all_orders(): void
    {
        $shop = $this->makeShop('billed');
        $table = $this->makeCustomerTable($shop, 13);
        $session = $this->openActiveSession($shop, $table);

        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $this->markAdditionPrintedAt($session, Carbon::parse('2026-01-15 12:30:00'));

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Billed, $tile->uiStatus);
        $this->assertFalse($tile->hasOrderAfterAdditionPrinted, 'Billed であって誤爆 Alert してはならない');
        $this->assertNotNull($tile->lastAdditionPrintedAt);
    }

    /**
     * [境界テスト] SQL は strict `>` 比較なので、同時刻は Alert を**引き起こしてはならない**。
     * (現実に同秒で記録されることは稀だが、秒精度 DB で安全側に倒すための砦)
     */
    public function test_billed_holds_when_order_created_at_equals_print_time_exactly(): void
    {
        $shop = $this->makeShop('boundary-equal');
        $table = $this->makeCustomerTable($shop, 14);
        $session = $this->openActiveSession($shop, $table);

        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, $printAt);
        $this->markAdditionPrintedAt($session, $printAt);

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Billed, $tile->uiStatus);
        $this->assertFalse($tile->hasOrderAfterAdditionPrinted);
    }

    /**
     * [境界テスト] 印刷より 1 秒前に作成された注文が後追いで存在していた場合は Billed のまま。
     */
    public function test_billed_holds_when_order_created_one_second_before_print(): void
    {
        $shop = $this->makeShop('boundary-before');
        $table = $this->makeCustomerTable($shop, 15);
        $session = $this->openActiveSession($shop, $table);

        $beforePrint = Carbon::parse('2026-01-15 12:29:59');
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, $beforePrint);
        $this->markAdditionPrintedAt($session, $printAt);

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Billed, $tile->uiStatus);
    }

    /**
     * [現場死守テスト A] お会計直後「やっぱりデザートを」→ 1 秒後の PosOrder で Alert 確定。
     */
    public function test_alert_fires_when_new_pos_order_is_one_second_after_print(): void
    {
        $shop = $this->makeShop('alert-order-1s');
        $table = $this->makeCustomerTable($shop, 16);
        $session = $this->openActiveSession($shop, $table);

        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->markAdditionPrintedAt($session, $printAt);

        $oneSecondLater = $printAt->copy()->addSecond();
        $this->placeOrderAt($shop, $session, OrderStatus::Placed, $oneSecondLater);

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Alert, $tile->uiStatus);
        $this->assertTrue($tile->hasOrderAfterAdditionPrinted);
    }

    /**
     * [現場死守テスト B] 既存 PosOrder に後から OrderLine が追加されたケースも Alert。
     */
    public function test_alert_fires_when_new_order_line_is_one_second_after_print(): void
    {
        $shop = $this->makeShop('alert-line-1s');
        $table = $this->makeCustomerTable($shop, 17);
        $session = $this->openActiveSession($shop, $table);

        $order = $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->markAdditionPrintedAt($session, $printAt);

        $this->addLineAt($shop, $order, $printAt->copy()->addSecond());

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Alert, $tile->uiStatus);
        $this->assertTrue($tile->hasOrderAfterAdditionPrinted);
    }

    /**
     * [誤爆防止] 印刷後に作られた注文が Voided だった場合、Alert に上がってはならない。
     * (voided は `status != 'voided'` で SQL 側から除外される)
     */
    public function test_billed_holds_when_only_voided_order_exists_after_print(): void
    {
        $shop = $this->makeShop('voided-after');
        $table = $this->makeCustomerTable($shop, 18);
        $session = $this->openActiveSession($shop, $table);

        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->markAdditionPrintedAt($session, $printAt);

        $this->placeOrderAt($shop, $session, OrderStatus::Voided, $printAt->copy()->addSeconds(10));

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Billed, $tile->uiStatus);
        $this->assertFalse($tile->hasOrderAfterAdditionPrinted);
    }

    /**
     * Billed 自体は「印刷済みかつ placed 未確認が存在しない」という定義。
     * 印刷後に Placed 注文を追加した場合、Resolver の優先順位 (Alert > Pending) により Alert になる。
     */
    public function test_alert_takes_priority_over_pending_when_printed_and_placed_exists_after(): void
    {
        $shop = $this->makeShop('alert-over-pending');
        $table = $this->makeCustomerTable($shop, 19);
        $session = $this->openActiveSession($shop, $table);

        $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::parse('2026-01-15 12:00:00'));
        $printAt = Carbon::parse('2026-01-15 12:30:00');
        $this->markAdditionPrintedAt($session, $printAt);
        $this->placeOrderAt($shop, $session, OrderStatus::Placed, $printAt->copy()->addMinute());

        $tile = $this->firstTile($shop->id);

        $this->assertSame(TableUiStatus::Alert, $tile->uiStatus);
    }

    public function test_category_resolves_from_id_ranges_customer_staff_takeaway(): void
    {
        $shop = $this->makeShop('cat-range');
        $this->makeCustomerTable($shop, 10);
        $this->makeCustomerTable($shop, 29);
        $this->makeStaffTable($shop, 100);
        $this->makeStaffTable($shop, 109);
        $this->makeTakeawayTable($shop, 200);
        $this->makeTakeawayTable($shop, 219);

        $tiles = app(TableDashboardQueryService::class)
            ->getDashboardData($shop->id)
            ->tiles;

        $byId = [];
        foreach ($tiles as $t) {
            $byId[$t->restaurantTableId] = $t->category;
        }

        $this->assertSame(TableCategory::Customer, $byId[10]);
        $this->assertSame(TableCategory::Customer, $byId[29]);
        $this->assertSame(TableCategory::Staff, $byId[100]);
        $this->assertSame(TableCategory::Staff, $byId[109]);
        $this->assertSame(TableCategory::Takeaway, $byId[200]);
        $this->assertSame(TableCategory::Takeaway, $byId[219]);
    }

    /**
     * 固定バケツ範囲外 ID は SQL 上は返るが category=null で UI から落ちる想定。
     * 旧互換テストが `create()` で auto-increment ID を使うパスが壊れないための安全網。
     */
    public function test_out_of_range_id_tile_has_null_category_but_still_returned(): void
    {
        $shop = $this->makeShop('legacy-id');
        \DB::table('restaurant_tables')->insert([
            'id' => 9999,
            'shop_id' => $shop->id,
            'name' => 'Legacy',
            'qr_token' => 'legacy-qr-'.bin2hex(random_bytes(6)),
            'sort_order' => 0,
            'is_active' => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        $tiles = app(TableDashboardQueryService::class)->getDashboardData($shop->id)->tiles;

        $this->assertCount(1, $tiles);
        $this->assertSame(9999, $tiles[0]->restaurantTableId);
        $this->assertNull($tiles[0]->category);
        $this->assertSame(TableUiStatus::Free, $tiles[0]->uiStatus);
    }

    private function firstTile(int $shopId): TableTileAggregate
    {
        $tiles = app(TableDashboardQueryService::class)->getDashboardData($shopId)->tiles;
        $this->assertNotEmpty($tiles, 'No tiles returned for shop '.$shopId);

        return $tiles[0];
    }
}
