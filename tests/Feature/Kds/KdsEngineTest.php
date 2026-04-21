<?php

namespace Tests\Feature\Kds;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Actions\Kds\UpdateOrderLineStatusAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Events\Kds\OrderConfirmedBroadcast;
use App\Exceptions\RevisionConflictException;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use App\Services\Kds\KdsQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

class KdsEngineTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    /**
     * @return array{shop: Shop, category: MenuCategory, item: MenuItem, table: RestaurantTable}
     */
    private function seedShopTableAndItem(): array
    {
        $shop = Shop::query()->create([
            'name' => 'KDS Shop',
            'slug' => 'kds-shop',
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Ramen',
            'slug' => 'ramen',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'K-1',
            'qr_token' => 'kds-qr-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $this->createActiveTableSession($shop, $table);

        return [
            'shop' => $shop,
            'category' => $category,
            'item' => $item,
            'table' => $table,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(Shop $shop, RestaurantTable $table, int $itemId, string $idempotencyKey): array
    {
        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'guest-sess-kds',
            'context' => [
                'tenantSlug' => (string) $shop->slug,
                'tableToken' => (string) $table->qr_token,
                'locale' => 'en',
            ],
            'catalogFingerprint' => [
                'currency' => 'TND',
                'priceDivisor' => 1000,
            ],
            'lines' => [[
                'lineId' => (string) Str::uuid(),
                'mergeKey' => $itemId.'|__none__|',
                'itemId' => (string) $itemId,
                'titleSnapshot' => 'Ramen',
                'kitchenNameSnapshot' => 'Ramen',
                'styleId' => null,
                'styleNameSnapshot' => null,
                'stylePriceMinor' => 1000,
                'toppingSnapshots' => [],
                'unitLineTotalMinor' => 1000,
                'qty' => 1,
                'lineTotalMinor' => 1000,
                'note' => '',
            ]],
            'totals' => [
                'currency' => 'TND',
                'priceDivisor' => 1000,
                'subtotalMinor' => 1000,
            ],
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    public function test_recu_confirms_orders_and_dispatches_broadcast_after_commit(): void
    {
        Event::fake([OrderConfirmedBroadcast::class]);

        $p = $this->seedShopTableAndItem();
        $submit = app(SubmitGuestOrderAction::class);
        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, 'kds-idem-'.bin2hex(random_bytes(8)))
        );
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();
        $session->refresh();
        $rev = (int) $session->session_revision;

        $n = app(RecuPlacedOrdersForSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, $rev);
        // afterResponse: テスト中は HTTP レスポンス相当の terminate を明示実行する。
        $this->app->terminate();

        $this->assertSame(1, $n);

        $pos = PosOrder::query()->whereKey($r->posOrderId)->sole();
        $this->assertSame(OrderStatus::Confirmed, $pos->status);
        $line = OrderLine::query()->where('order_id', $pos->id)->sole();
        $this->assertSame(OrderLineStatus::Confirmed, $line->status);
        $this->assertSame(2, (int) $line->line_revision);

        // ベル（OrderConfirmedBroadcast）は afterResponse で 1 回ディスパッチされる。
        Event::assertDispatched(
            OrderConfirmedBroadcast::class,
            fn (OrderConfirmedBroadcast $e) => $e->shopId === (int) $p['shop']->id
        );
    }

    public function test_update_order_line_status_advances_revision_or_conflict(): void
    {
        $p = $this->seedShopTableAndItem();
        $submit = app(SubmitGuestOrderAction::class);
        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, 'kds-idem-'.bin2hex(random_bytes(8)))
        );
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();
        $session->refresh();
        app(RecuPlacedOrdersForSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, (int) $session->session_revision);

        $line = OrderLine::query()->where('order_id', $r->posOrderId)->sole();
        $this->assertSame(2, (int) $line->line_revision);

        $updated = app(UpdateOrderLineStatusAction::class)->execute(
            (int) $line->id,
            OrderLineStatus::Cooking->value,
            (int) $line->line_revision
        );
        $this->assertSame(OrderLineStatus::Cooking, $updated->status);
        $this->assertSame(3, (int) $updated->line_revision);

        $this->expectException(RevisionConflictException::class);
        app(UpdateOrderLineStatusAction::class)->execute(
            (int) $line->id,
            OrderLineStatus::Served->value,
            2
        );
    }

    public function test_kds_query_service_keyset_pull(): void
    {
        $p = $this->seedShopTableAndItem();
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->sole();

        $order = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 3000,
            'placed_at' => now(),
        ]);

        $t1 = now()->subSeconds(10);
        $t2 = now()->subSeconds(5);

        $lineA = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 1000,
            'line_total_minor' => 1000,
            'snapshot_name' => 'A',
            'snapshot_kitchen_name' => 'A',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Confirmed,
            'line_revision' => 1,
            'created_at' => $t1,
            'updated_at' => $t1,
        ]);
        $lineA->forceFill(['updated_at' => $t1])->saveQuietly();

        $lineB = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $p['item']->id,
            'qty' => 1,
            'unit_price_minor' => 2000,
            'line_total_minor' => 2000,
            'snapshot_name' => 'B',
            'snapshot_kitchen_name' => 'B',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Cooking,
            'line_revision' => 1,
            'created_at' => $t2,
            'updated_at' => $t2,
        ]);
        $lineB->forceFill(['updated_at' => $t2])->saveQuietly();

        $svc = app(KdsQueryService::class);
        $first = $svc->pullActiveTickets((int) $p['shop']->id, null, null, 10);
        $this->assertCount(2, $first);
        $this->assertTrue($first[0]->is($lineA));
        $this->assertTrue($first[1]->is($lineB));

        $cursorAt = $first[0]->updated_at->toIso8601String();
        $second = $svc->pullActiveTickets((int) $p['shop']->id, $cursorAt, (int) $first[0]->id, 10);
        $this->assertCount(1, $second);
        $this->assertTrue($second[0]->is($lineB));
    }
}
