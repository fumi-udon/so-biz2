<?php

namespace Tests\Feature\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Actions\RadTable\CheckoutTableSessionAction;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\GuestOrderIdempotency;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

/**
 * フェーズ3: 同一冪等キー連打・会計との競合を、DB ロックとトランザクション境界で防げることの検証。
 *
 * 真の OS レベル並列は PHPUnit 単体では再現しづらいため、同一接続上で確実に再現できる
 * 「連打」「会計後の拒否」「未確定注文での会計拒否」を中心に検証する。
 */
class OrderRaceConditionTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    /**
     * @return array{shop: Shop, category: MenuCategory, table: RestaurantTable, item: MenuItem}
     */
    private function seedRaceFixtures(): array
    {
        $shop = Shop::query()->create([
            'name' => 'Race Shop',
            'slug' => 'race-shop-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'm-'.bin2hex(random_bytes(4)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T1',
            'qr_token' => 'qr-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $this->createActiveTableSession($shop, $table);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Dish',
            'slug' => 'dish-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 9_000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        return compact('shop', 'category', 'table', 'item');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Shop $shop, RestaurantTable $table, int $itemId, string $idempotencyKey): array
    {
        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'sess-race',
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
                'mergeKey' => $itemId.'|k',
                'itemId' => (string) $itemId,
                'titleSnapshot' => 'X',
                'kitchenNameSnapshot' => 'X',
                'styleId' => null,
                'styleNameSnapshot' => null,
                'stylePriceMinor' => 1,
                'toppingSnapshots' => [],
                'unitLineTotalMinor' => 1,
                'qty' => 1,
                'lineTotalMinor' => 1,
                'note' => '',
            ]],
            'totals' => [
                'currency' => 'TND',
                'priceDivisor' => 1000,
                'subtotalMinor' => 0,
            ],
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    public function test_triple_submit_same_idempotency_key_yields_single_order_and_stable_result(): void
    {
        $p = $this->seedRaceFixtures();
        $action = app(SubmitGuestOrderAction::class);
        $key = 'idem-race-'.bin2hex(random_bytes(16));
        $pl = $this->payload($p['shop'], $p['table'], (int) $p['item']->id, $key);

        $ids = [];
        for ($i = 0; $i < 3; $i += 1) {
            $r = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
            $ids[] = $r->posOrderId;
        }

        $this->assertSame($ids[0], $ids[1]);
        $this->assertSame($ids[1], $ids[2]);
        $this->assertSame(1, PosOrder::query()->count());
        $this->assertSame(1, GuestOrderIdempotency::query()->count());
        $this->assertSame(
            (int) $ids[0],
            (int) GuestOrderIdempotency::query()->value('pos_order_id')
        );
    }

    public function test_distinct_idempotency_keys_on_same_session_create_separate_orders(): void
    {
        $p = $this->seedRaceFixtures();
        $action = app(SubmitGuestOrderAction::class);
        $a = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, 'key-a-'.bin2hex(random_bytes(8)))
        );
        $b = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, 'key-b-'.bin2hex(random_bytes(8)))
        );

        $this->assertNotSame($a->posOrderId, $b->posOrderId);
        $this->assertSame(2, PosOrder::query()->count());
        $this->assertSame(2, GuestOrderIdempotency::query()->count());
    }

    public function test_checkout_blocked_while_unacked_placed_orders_preempt_close_race(): void
    {
        $p = $this->seedRaceFixtures();
        $submit = app(SubmitGuestOrderAction::class);
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();

        $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, 'u1-'.bin2hex(random_bytes(8)))
        );
        $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, 'u2-'.bin2hex(random_bytes(8)))
        );

        $this->assertSame(2, PosOrder::query()->where('table_session_id', $session->id)->where('status', OrderStatus::Placed)->count());
        $this->assertSame(TableSessionStatus::Active, TableSession::query()->find($session->id)->status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('rad_table.cannot_close_with_unacked'));
        $rev = (int) TableSession::query()->whereKey($session->id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, $rev);
    }

    public function test_guest_submit_after_checkout_auto_opens_a_new_active_session_without_touching_the_closed_one(): void
    {
        $p = $this->seedRaceFixtures();
        $submit = app(SubmitGuestOrderAction::class);
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();

        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, 'close-'.bin2hex(random_bytes(8)))
        );
        $order = PosOrder::query()->findOrFail($r->posOrderId);
        $order->update(['status' => OrderStatus::Confirmed]);

        $rev = (int) TableSession::query()->whereKey($session->id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, $rev);
        $this->assertSame(TableSessionStatus::Closed, TableSession::query()->find($session->id)->status);

        $beforeOrders = PosOrder::query()->count();

        $r2 = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, 'after-close-'.bin2hex(random_bytes(8)))
        );

        $this->assertGreaterThan(0, $r2->posOrderId);
        $this->assertSame($beforeOrders + 1, PosOrder::query()->count());

        // Existing closed session must be untouched.
        $this->assertSame(TableSessionStatus::Closed, TableSession::query()->find($session->id)->status);

        $newOrder = PosOrder::query()->findOrFail($r2->posOrderId);
        $this->assertNotSame((int) $session->id, (int) $newOrder->table_session_id);
        $newSession = TableSession::query()->findOrFail($newOrder->table_session_id);
        $this->assertSame(TableSessionStatus::Active, $newSession->status);
        $this->assertSame((int) $p['table']->id, (int) $newSession->restaurant_table_id);
    }

    public function test_back_to_back_guest_submits_on_empty_table_share_one_auto_created_active_session(): void
    {
        $shop = Shop::query()->create([
            'name' => 'Auto Shop',
            'slug' => 'auto-shop-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'M',
            'slug' => 'mauto-'.bin2hex(random_bytes(4)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T-empty',
            'qr_token' => 'qr-empty-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Auto Dish',
            'slug' => 'auto-dish-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 1_000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->assertSame(0, TableSession::query()->where('restaurant_table_id', $table->id)->count());

        $submit = app(SubmitGuestOrderAction::class);
        $r1 = $submit->execute(
            (string) $shop->slug,
            (string) $table->qr_token,
            $this->payload($shop, $table, (int) $item->id, 'auto1-'.bin2hex(random_bytes(8)))
        );
        $r2 = $submit->execute(
            (string) $shop->slug,
            (string) $table->qr_token,
            $this->payload($shop, $table, (int) $item->id, 'auto2-'.bin2hex(random_bytes(8)))
        );

        $this->assertNotSame($r1->posOrderId, $r2->posOrderId);
        $this->assertSame(
            1,
            TableSession::query()
                ->where('restaurant_table_id', $table->id)
                ->where('status', TableSessionStatus::Active)
                ->count(),
            'A second guest scan must JOIN the existing auto-created Active session, never spawn a duplicate.'
        );
        $sessionId = (int) PosOrder::query()->find($r1->posOrderId)->table_session_id;
        $this->assertSame($sessionId, (int) PosOrder::query()->find($r2->posOrderId)->table_session_id);
    }

    public function test_guest_order_idempotency_row_is_unique_per_session_and_key(): void
    {
        $p = $this->seedRaceFixtures();
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();
        $order = PosOrder::query()->create([
            'shop_id' => $p['shop']->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Placed,
            'total_price_minor' => 1000,
            'placed_at' => now(),
        ]);
        GuestOrderIdempotency::query()->create([
            'table_session_id' => $session->id,
            'idempotency_key' => 'dup-test-key',
            'pos_order_id' => $order->id,
        ]);

        $this->expectException(QueryException::class);
        GuestOrderIdempotency::query()->create([
            'table_session_id' => $session->id,
            'idempotency_key' => 'dup-test-key',
            'pos_order_id' => $order->id,
        ]);
    }
}
