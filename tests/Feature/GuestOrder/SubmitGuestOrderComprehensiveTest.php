<?php

namespace Tests\Feature\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Enums\TableSessionStatus;
use App\Exceptions\GuestOrderForbiddenException;
use App\Exceptions\GuestOrderValidationException;
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

class SubmitGuestOrderComprehensiveTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    private function newIdemKey(): string
    {
        return 'iden-comp-'.bin2hex(random_bytes(16));
    }

    /**
     * @return array{shop: Shop, category: MenuCategory, table: RestaurantTable}
     */
    private function seedShopAndTable(): array
    {
        $shop = Shop::query()->create([
            'name' => 'Comp Test Shop',
            'slug' => 'comp-test-shop',
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Mains',
            'slug' => 'mains',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T-Comp',
            'qr_token' => 'comp-qr-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $this->createActiveTableSession($shop, $table);

        return ['shop' => $shop, 'category' => $category, 'table' => $table];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function payload(Shop $shop, RestaurantTable $table, array $lines, string $idempotencyKey, ?string $contextTenant = null, ?string $contextToken = null): array
    {
        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'sess-test',
            'context' => [
                'tenantSlug' => $contextTenant ?? (string) $shop->slug,
                'tableToken' => $contextToken ?? (string) $table->qr_token,
                'locale' => 'en',
            ],
            'catalogFingerprint' => [
                'currency' => 'TND',
                'priceDivisor' => 1000,
            ],
            'lines' => $lines,
            'totals' => [
                'currency' => 'TND',
                'priceDivisor' => 1000,
                'subtotalMinor' => 0,
            ],
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(
        int $itemId,
        int $unitLineTotalMinorTamp,
        int $lineTotalTamp,
        int $qty = 1,
        ?string $styleId = null,
        array $toppingSnapshots = []
    ): array {
        return [
            'lineId' => (string) Str::uuid(),
            'mergeKey' => $itemId.'|k',
            'itemId' => (string) $itemId,
            'titleSnapshot' => 'X',
            'kitchenNameSnapshot' => 'X',
            'styleId' => $styleId,
            'styleNameSnapshot' => null,
            'stylePriceMinor' => 1,
            'toppingSnapshots' => $toppingSnapshots,
            'unitLineTotalMinor' => $unitLineTotalMinorTamp,
            'qty' => $qty,
            'lineTotalMinor' => $lineTotalTamp,
            'note' => '',
        ];
    }

    public function test_ignores_client_tampered_line_prices_uses_server_totals(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Pizza',
            'slug' => 'pizza',
            'from_price_minor' => 15000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $action = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line(
                (int) $item->id,
                1,
                1,
                2
            )],
            $k
        );

        $result = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );

        $order = PosOrder::query()->findOrFail($result->posOrderId);
        $this->assertSame(30000, (int) $order->total_price_minor, '2 × 15_000; client sent 1');

        $line = OrderLine::query()->where('order_id', $order->id)->sole();
        $this->assertSame(30000, (int) $line->line_total_minor);
        $this->assertSame(15000, (int) $line->unit_price_minor);
    }

    public function test_ignores_tampered_topping_price_field_uses_master_delta(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Ramen',
            'slug' => 'ramen',
            'from_price_minor' => 10000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => false],
                'styles' => [],
                'toppings' => [
                    [
                        'id' => 'egg',
                        'name' => 'Egg',
                        'price_delta_minor' => 2000,
                    ],
                ],
            ],
        ]);
        $action = app(SubmitGuestOrderAction::class);
        $k = $this->newIdemKey();
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line(
                (int) $item->id,
                0,
                0,
                1,
                null,
                [['id' => 'egg', 'name' => 'Egg', 'priceDeltaMinor' => 1]]
            )],
            $k
        );

        $result = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );

        $line = OrderLine::query()->where('order_id', $result->posOrderId)->sole();
        $this->assertSame(10000 + 2000, (int) $line->unit_price_minor, 'Topping client delta 1 ignored; master 2000 used');
        $this->assertSame(12000, (int) $line->line_total_minor);
    }

    public function test_rejects_unknown_menu_item_id(): void
    {
        $p = $this->seedShopAndTable();
        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line(9_999_999, 100, 100)],
            $this->newIdemKey()
        );
        $this->expectException(GuestOrderValidationException::class);
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_rejects_unknown_menu_item_slug(): void
    {
        $p = $this->seedShopAndTable();
        $action = app(SubmitGuestOrderAction::class);
        $l = $this->line(1, 1, 1);
        $l['itemId'] = 'definitely-missing-slug-xyz';
        $pl = $this->payload($p['shop'], $p['table'], [$l], $this->newIdemKey());
        $this->expectException(GuestOrderValidationException::class);
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_rejects_invalid_style_id(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Bowl',
            'slug' => 'bowl',
            'from_price_minor' => 5000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => false],
                'styles' => [
                    ['id' => 'm', 'name' => 'M', 'price_minor' => 5000],
                ],
                'toppings' => [],
            ],
        ]);
        $action = app(SubmitGuestOrderAction::class);
        $l = $this->line((int) $item->id, 5000, 5000, 1, 'hacker-style');
        $pl = $this->payload($p['shop'], $p['table'], [$l], $this->newIdemKey());
        $this->expectException(GuestOrderValidationException::class);
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_rejects_invalid_topping_id(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Noodle',
            'slug' => 'noodle',
            'from_price_minor' => 4000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => false],
                'styles' => [],
                'toppings' => [['id' => 'onion', 'name' => 'Onion', 'price_delta_minor' => 500]],
            ],
        ]);
        $action = app(SubmitGuestOrderAction::class);
        $l = $this->line(
            (int) $item->id,
            4000,
            4000,
            1,
            null,
            [['id' => 'stolen', 'name' => 'Nope', 'priceDeltaMinor' => 0]]
        );
        $pl = $this->payload($p['shop'], $p['table'], [$l], $this->newIdemKey());
        $this->expectException(GuestOrderValidationException::class);
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_rejects_inactive_menu_item(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Sold out',
            'slug' => 'sold-out',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => false,
        ]);
        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey()
        );
        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('guest.item_unavailable'));
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_inactive_item_after_resolved_caught_by_row_lock_recheck(): void
    {
        $p = $this->seedShopAndTable();
        $itemA = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'A',
            'slug' => 'line-a',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $itemB = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'B',
            'slug' => 'line-b',
            'from_price_minor' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $itemA->id, 1000, 1000), $this->line((int) $itemB->id, 2000, 2000)],
            $this->newIdemKey()
        );
        $itemB->is_active = false;
        $itemB->save();

        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('guest.item_unavailable'));
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
        $this->assertSame(0, PosOrder::query()->count());
    }

    public function test_invalid_tenant_slug_throws_forbidden(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'C',
            'slug' => 'c',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey(),
            'wrong-tenant',
            (string) $p['table']->qr_token
        );
        $action = app(SubmitGuestOrderAction::class);
        $this->expectException(GuestOrderForbiddenException::class);
        $this->expectExceptionMessage(__('Shop not found.'));
        $action->execute('wrong-tenant', (string) $p['table']->qr_token, $pl);
    }

    public function test_inactive_shop_throws_forbidden(): void
    {
        $p = $this->seedShopAndTable();
        $p['shop']->is_active = false;
        $p['shop']->save();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'C',
            'slug' => 'c',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey()
        );
        $action = app(SubmitGuestOrderAction::class);
        $this->expectException(GuestOrderForbiddenException::class);
        $this->expectExceptionMessage(__('Shop not found.'));
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_invalid_table_token_throws_validation(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'C',
            'slug' => 'c',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey(),
            (string) $p['shop']->slug,
            'definitely-bad-qr'
        );
        $action = app(SubmitGuestOrderAction::class);
        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('Unknown table.'));
        $action->execute(
            (string) $p['shop']->slug,
            'definitely-bad-qr',
            $pl
        );
    }

    public function test_inactive_restaurant_table_rejects_order(): void
    {
        $p = $this->seedShopAndTable();
        $p['table']->is_active = false;
        $p['table']->save();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'C',
            'slug' => 'c',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey()
        );
        $action = app(SubmitGuestOrderAction::class);
        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('Unknown table.'));
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_payload_context_mismatch_with_route_fails(): void
    {
        $p = $this->seedShopAndTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'C',
            'slug' => 'c',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey(),
            'other-tenant',
            (string) $p['table']->qr_token
        );
        $action = app(SubmitGuestOrderAction::class);
        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('Session context mismatch.'));
        $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
    }

    public function test_only_closed_table_sessions_auto_open_a_new_active_session_for_guest(): void
    {
        $p = $this->seedShopAndTable();
        TableSession::query()->where('restaurant_table_id', $p['table']->id)->delete();

        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'D',
            'slug' => 'd',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $closed = TableSession::query()->create([
            'shop_id' => $p['shop']->id,
            'restaurant_table_id' => $p['table']->id,
            'token' => Str::lower(Str::random(48)),
            'status' => TableSessionStatus::Closed,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(30),
        ]);
        $this->assertSame(
            0,
            TableSession::query()
                ->where('restaurant_table_id', $p['table']->id)
                ->where('status', TableSessionStatus::Active)
                ->count()
        );

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey()
        );

        $r = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );

        $this->assertGreaterThan(0, $r->posOrderId);
        $this->assertSame(TableSessionStatus::Closed, TableSession::query()->find($closed->id)->status);
        $this->assertSame(
            1,
            TableSession::query()
                ->where('restaurant_table_id', $p['table']->id)
                ->where('status', TableSessionStatus::Active)
                ->count()
        );
    }

    public function test_guest_order_after_closed_sessions_succeeds_when_staff_opens_active_session(): void
    {
        $p = $this->seedShopAndTable();
        TableSession::query()->where('restaurant_table_id', $p['table']->id)->delete();
        TableSession::query()->create([
            'shop_id' => $p['shop']->id,
            'restaurant_table_id' => $p['table']->id,
            'token' => Str::lower(Str::random(48)),
            'status' => TableSessionStatus::Closed,
            'opened_at' => now()->subHour(),
            'closed_at' => now()->subMinutes(30),
        ]);

        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'E',
            'slug' => 'e',
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload(
            $p['shop'],
            $p['table'],
            [$this->line((int) $item->id, 1000, 1000)],
            $this->newIdemKey()
        );

        $this->createActiveTableSession($p['shop'], $p['table']);
        $r = $action->execute(
            (string) $p['shop']->slug,
            (string) $p['table']->qr_token,
            $pl
        );
        $this->assertGreaterThan(0, $r->posOrderId);
        $this->assertSame(
            1,
            TableSession::query()
                ->where('restaurant_table_id', $p['table']->id)
                ->where('status', TableSessionStatus::Active)
                ->count()
        );
    }
}
