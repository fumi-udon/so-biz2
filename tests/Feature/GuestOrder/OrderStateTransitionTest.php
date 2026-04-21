<?php

namespace Tests\Feature\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Actions\RadTable\CheckoutTableSessionAction;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\GuestOrderIdempotency;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

/**
 * フェーズ2: 卓セッションの状態とゲスト注文の整合性。
 */
class OrderStateTransitionTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    private function idemKey(): string
    {
        return 'state-tr-'.bin2hex(random_bytes(16));
    }

    /**
     * @return array{shop: Shop, category: MenuCategory, table: RestaurantTable, item: MenuItem}
     */
    private function seedBase(): array
    {
        $shop = Shop::query()->create([
            'name' => 'State Shop',
            'slug' => 'state-shop-'.bin2hex(random_bytes(4)),
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
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Item',
            'slug' => 'item-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 5_000,
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
            'clientSessionId' => 'sess-state',
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

    public function test_guest_order_auto_creates_active_session_when_none_exists(): void
    {
        $p = $this->seedBase();
        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], (int) $p['item']->id, $this->idemKey());

        $this->assertSame(0, TableSession::query()->count());
        $this->assertSame(0, PosOrder::query()->count());

        $result = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);

        $this->assertGreaterThan(0, $result->posOrderId);
        $session = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();
        $order = PosOrder::query()->findOrFail($result->posOrderId);
        $this->assertSame((int) $session->id, (int) $order->table_session_id);
        $this->assertSame(1, OrderLine::query()->count());
        $this->assertSame(1, GuestOrderIdempotency::query()->count());
    }

    public function test_guest_order_auto_opens_new_active_session_when_only_closed_history_exists(): void
    {
        $p = $this->seedBase();
        $closed = TableSession::query()->create([
            'shop_id' => $p['shop']->id,
            'restaurant_table_id' => $p['table']->id,
            'token' => Str::lower(Str::random(48)),
            'status' => TableSessionStatus::Closed,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinute(),
        ]);

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], (int) $p['item']->id, $this->idemKey());

        $r = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);

        $this->assertGreaterThan(0, $r->posOrderId);
        $this->assertSame(
            TableSessionStatus::Closed,
            TableSession::query()->find($closed->id)->status,
            'Existing Closed session must remain Closed.'
        );
        $active = TableSession::query()
            ->where('restaurant_table_id', $p['table']->id)
            ->where('status', TableSessionStatus::Active)
            ->sole();
        $order = PosOrder::query()->findOrFail($r->posOrderId);
        $this->assertSame((int) $active->id, (int) $order->table_session_id);
        $this->assertNotSame((int) $closed->id, (int) $order->table_session_id);
    }

    public function test_after_checkout_guest_order_auto_opens_a_brand_new_active_session(): void
    {
        $p = $this->seedBase();
        $session = $this->createActiveTableSession($p['shop'], $p['table']);
        $submit = app(SubmitGuestOrderAction::class);
        $k = $this->idemKey();
        $r = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, $k)
        );
        $order = PosOrder::query()->findOrFail($r->posOrderId);
        $order->update(['status' => OrderStatus::Confirmed]);

        $rev = (int) TableSession::query()->whereKey($session->id)->value('session_revision');
        app(CheckoutTableSessionAction::class)->execute((int) $p['shop']->id, (int) $session->id, $rev);

        $this->assertSame(TableSessionStatus::Closed, TableSession::query()->find($session->id)->status);

        $r2 = $submit->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, $this->idemKey())
        );

        $this->assertGreaterThan(0, $r2->posOrderId);
        $newOrder = PosOrder::query()->findOrFail($r2->posOrderId);
        $this->assertNotSame((int) $session->id, (int) $newOrder->table_session_id);
        $newSession = TableSession::query()->find($newOrder->table_session_id);
        $this->assertSame(TableSessionStatus::Active, $newSession->status);
    }

    public function test_placed_status_on_pos_order_and_order_line_when_active_session(): void
    {
        $p = $this->seedBase();
        $this->createActiveTableSession($p['shop'], $p['table']);

        $action = app(SubmitGuestOrderAction::class);
        $result = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $this->payload($p['shop'], $p['table'], (int) $p['item']->id, $this->idemKey())
        );

        $pos = PosOrder::query()->findOrFail($result->posOrderId);
        $line = OrderLine::query()->where('order_id', $pos->id)->sole();

        $this->assertSame(OrderStatus::Placed, $pos->status);
        $this->assertSame(OrderLineStatus::Placed, $line->status);
    }
}
