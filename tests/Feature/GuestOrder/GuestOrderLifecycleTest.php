<?php

namespace Tests\Feature\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Actions\RadTable\CheckoutTableSessionAction;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Exceptions\GuestOrderValidationException;
use App\Exceptions\RevisionConflictException;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

/**
 * Phase 2.8: guest → session → submit → (POS) close. Serial integration tests
 * for restaurant edge cases. True interleaved DB transactions require a second
 * client connection; lock ordering in actions is the primary concurrent-safety
 * guarantee for the same table.
 */
class GuestOrderLifecycleTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    private function newIdemKey(): string
    {
        return 'life-idem-'.bin2hex(random_bytes(12));
    }

    /**
     * @return array{shop: Shop, category: MenuCategory, item: MenuItem, table: RestaurantTable}
     */
    private function seedShopTableAndItem(): array
    {
        $shop = Shop::query()->create([
            'name' => 'Lifecycle Shop',
            'slug' => 'lifecycle-shop',
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
            'name' => 'Set Menu',
            'slug' => 'set-menu',
            'from_price_minor' => 25000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'L-1',
            'qr_token' => 'life-qr-'.bin2hex(random_bytes(8)),
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
    private function basePayload(Shop $shop, RestaurantTable $table, int $itemId, string $idempotencyKey, ?string $contextTableToken = null): array
    {
        $token = $contextTableToken ?? (string) $table->qr_token;

        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'guest-sess-1',
            'context' => [
                'tenantSlug' => (string) $shop->slug,
                'tableToken' => $token,
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
                'titleSnapshot' => 'Set',
                'kitchenNameSnapshot' => 'Set',
                'styleId' => null,
                'styleNameSnapshot' => null,
                'stylePriceMinor' => 25000,
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

    public function test_happy_path_session_created_and_order_bound_to_table_session(): void
    {
        $p = $this->seedShopTableAndItem();
        $action = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $pl = $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k);

        $r = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );

        $this->assertGreaterThan(0, $r->posOrderId);
        $order = PosOrder::query()->whereKey($r->posOrderId)->sole();
        $this->assertSame(25000, (int) $order->total_price_minor);

        $session = TableSession::query()->whereKey($order->table_session_id)->sole();
        $this->assertSame($p['table']->id, (int) $session->restaurant_table_id);
        $this->assertSame(TableSessionStatus::Active, $session->status);
        $this->assertSame(
            1,
            TableSession::query()
                ->where('restaurant_table_id', $p['table']->id)
                ->where('status', TableSessionStatus::Active)
                ->count()
        );
    }

    public function test_mismatching_table_token_in_payload_blocks_spoofed_cross_table_order(): void
    {
        $p = $this->seedShopTableAndItem();
        $tableB = RestaurantTable::query()->create([
            'shop_id' => $p['shop']->id,
            'name' => 'L-2',
            'qr_token' => 'other-table-'.bin2hex(random_bytes(8)),
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $action = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $pl = $this->basePayload(
            $p['shop'],
            $p['table'],
            (int) $p['item']->id,
            $k,
            (string) $tableB->qr_token
        );

        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('Session context mismatch.'));

        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_checkout_rejects_close_while_unacknowledged_placed_order_exists(): void
    {
        $p = $this->seedShopTableAndItem();
        $submit = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k)
        );
        $order = PosOrder::query()->whereKey($r->posOrderId)->sole();
        $this->assertSame(OrderStatus::Placed, $order->status);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(__('rad_table.cannot_close_with_unacked'));

        $rev = (int) TableSession::query()->whereKey($order->table_session_id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $order->table_session_id, $rev);
    }

    public function test_checkout_succeeds_after_pos_order_confirmed_then_session_closes(): void
    {
        $p = $this->seedShopTableAndItem();
        $submit = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k)
        );
        $order = PosOrder::query()->whereKey($r->posOrderId)->sole();
        $order->update(['status' => OrderStatus::Confirmed]);

        $rev = (int) TableSession::query()->whereKey($order->table_session_id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $order->table_session_id, $rev);

        $session = TableSession::query()->whereKey($order->table_session_id)->sole();
        $this->assertSame(TableSessionStatus::Closed, $session->status);
        $this->assertNotNull($session->closed_at);
    }

    public function test_after_checkout_guest_order_auto_opens_a_brand_new_active_session(): void
    {
        $p = $this->seedShopTableAndItem();
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();

        $rev = (int) TableSession::query()->whereKey($session->id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, $rev);
        $this->assertTrue(
            TableSession::query()
                ->where('restaurant_table_id', $p['table']->id)
                ->where('status', TableSessionStatus::Active)
                ->doesntExist()
        );

        $submit = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();

        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k)
        );

        $this->assertGreaterThan(0, $r->posOrderId);
        $order = PosOrder::query()->findOrFail($r->posOrderId);
        $this->assertNotSame((int) $session->id, (int) $order->table_session_id);
        $newSession = TableSession::query()->findOrFail($order->table_session_id);
        $this->assertSame(TableSessionStatus::Active, $newSession->status);
        $this->assertSame(
            1,
            TableSession::query()
                ->where('restaurant_table_id', $p['table']->id)
                ->where('status', TableSessionStatus::Active)
                ->count()
        );
    }

    public function test_after_checkout_new_active_session_allows_guest_order(): void
    {
        $p = $this->seedShopTableAndItem();
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();

        $submit = app(SubmitGuestOrderAction::class);
        $k1 = $this->newIdemKey();
        $r0 = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k1)
        );
        $order0 = PosOrder::query()->findOrFail($r0->posOrderId);
        $order0->update(['status' => OrderStatus::Confirmed]);

        $rev = (int) TableSession::query()->whereKey($session->id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, $rev);

        $newSession = $this->createActiveTableSession($p['shop'], $p['table']);
        $k2 = $this->newIdemKey();
        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k2)
        );

        $order = PosOrder::query()->whereKey($r->posOrderId)->sole();
        $this->assertSame((int) $newSession->id, (int) $order->table_session_id);
        $this->assertNotSame($session->id, $order->table_session_id);
    }

    public function test_idempotent_replay_returns_same_pos_order_without_duplicates(): void
    {
        $p = $this->seedShopTableAndItem();
        $action = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $pl = $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $k);

        $r1 = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
        $r2 = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);

        $this->assertSame($r1->posOrderId, $r2->posOrderId);
        $this->assertSame(1, PosOrder::query()->count());
    }

    public function test_consecutive_submits_with_distinct_idempotency_keys_create_two_orders(): void
    {
        $p = $this->seedShopTableAndItem();
        $action = app(SubmitGuestOrderAction::class);
        $p1 = $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $this->newIdemKey());
        $p2 = $this->basePayload($p['shop'], $p['table'], (int) $p['item']->id, $this->newIdemKey());

        $a = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $p1);
        $b = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $p2);

        $this->assertNotSame($a->posOrderId, $b->posOrderId);
        $this->assertSame(2, PosOrder::query()->count());
    }

    public function test_checkout_rejects_stale_session_revision(): void
    {
        $p = $this->seedShopTableAndItem();
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();

        $this->expectException(RevisionConflictException::class);
        app(CheckoutTableSessionAction::class)->execute(
            (int) $p['shop']->id,
            (int) $session->id,
            999_999
        );
    }
}
