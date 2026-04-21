<?php

namespace Tests\Unit;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Exceptions\GuestOrderValidationException;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Services\Pos\PosLineComputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

/**
 * Phase 1: 金額計算と注文ペイロードのバリデーション（ゲスト提出経路）。
 *
 * サーバーは {@see SubmitGuestOrderAction} 内で {@see PosLineComputationService} により
 * マスタ価格のみで再計算する（クライアントの unit/line 合計は参照しない）。
 */
class OrderCalculationTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    private function idemKey(): string
    {
        return 'oc-phase1-'.bin2hex(random_bytes(16));
    }

    /**
     * @return array{shop: Shop, category: MenuCategory, table: RestaurantTable}
     */
    private function seedShopTable(): array
    {
        $shop = Shop::query()->create([
            'name' => 'OC Shop',
            'slug' => 'oc-shop-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Cat',
            'slug' => 'cat-'.bin2hex(random_bytes(4)),
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

        return ['shop' => $shop, 'category' => $category, 'table' => $table];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, mixed>
     */
    private function payload(Shop $shop, RestaurantTable $table, array $lines, string $idempotencyKey): array
    {
        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'sess-oc',
            'context' => [
                'tenantSlug' => (string) $shop->slug,
                'tableToken' => (string) $table->qr_token,
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
        int $unitLineTotalMinorClientFake,
        int $lineTotalMinorClientFake,
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
            'unitLineTotalMinor' => $unitLineTotalMinorClientFake,
            'qty' => $qty,
            'lineTotalMinor' => $lineTotalMinorClientFake,
            'note' => '',
        ];
    }

    public function test_ignores_client_unit_and_line_totals_uses_master_only(): void
    {
        $p = $this->seedShopTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Steak',
            'slug' => 'steak-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 47_000,
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $action = app(SubmitGuestOrderAction::class);
        $k = $this->idemKey();

        $pl = $this->payload($p['shop'], $p['table'], [
            $this->line((int) $item->id, 1, 1, 1),
        ], $k);

        $result = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);

        $order = PosOrder::query()->findOrFail($result->posOrderId);
        $line = OrderLine::query()->where('order_id', $order->id)->sole();

        $this->assertSame(47_000, (int) $order->total_price_minor);
        $this->assertSame(47_000, (int) $line->unit_price_minor);
        $this->assertSame(47_000, (int) $line->line_total_minor);

        $k2 = $this->idemKey();
        $item2 = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Bulk',
            'slug' => 'bulk-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 11_000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $pl2 = $this->payload($p['shop'], $p['table'], [
            $this->line((int) $item2->id, 999_999, 999_999, 4),
        ], $k2);

        $r2 = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl2);
        $order2 = PosOrder::query()->findOrFail($r2->posOrderId);
        $line2 = OrderLine::query()->where('order_id', $order2->id)->sole();

        $this->assertSame(11_000 * 4, (int) $order2->total_price_minor);
        $this->assertSame(11_000, (int) $line2->unit_price_minor);
        $this->assertSame(44_000, (int) $line2->line_total_minor);
    }

    public function test_topping_totals_match_master_exactly_no_rounding_drift(): void
    {
        $p = $this->seedShopTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Combo',
            'slug' => 'combo-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 100_000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => false],
                'styles' => [],
                'toppings' => [
                    ['id' => 'a', 'name' => 'A', 'price_delta_minor' => 12_345],
                    ['id' => 'b', 'name' => 'B', 'price_delta_minor' => 6_789],
                    ['id' => 'c', 'name' => 'C', 'price_delta_minor' => 1],
                ],
            ],
        ]);

        $expectedUnit = 100_000 + 12_345 + 6_789 + 1;
        $svc = app(PosLineComputationService::class);
        $this->assertSame(
            $expectedUnit,
            $svc->computeUnitPriceMinor($item, [
                'styleId' => null,
                'toppingSnapshots' => [
                    ['id' => 'a'],
                    ['id' => 'b'],
                    ['id' => 'c'],
                ],
            ])
        );

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], [
            $this->line(
                (int) $item->id,
                0,
                0,
                1,
                null,
                [
                    ['id' => 'a', 'name' => 'A', 'priceDeltaMinor' => 0],
                    ['id' => 'b', 'name' => 'B', 'priceDeltaMinor' => 0],
                    ['id' => 'c', 'name' => 'C', 'priceDeltaMinor' => 999_999],
                ]
            ),
        ], $this->idemKey());

        $result = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
        $line = OrderLine::query()->where('order_id', $result->posOrderId)->sole();

        $this->assertSame($expectedUnit, (int) $line->unit_price_minor);
        $this->assertSame($expectedUnit, (int) $line->line_total_minor);
        $this->assertSame($expectedUnit, (int) PosOrder::query()->findOrFail($result->posOrderId)->total_price_minor);
    }

    public function test_half_dt_style_and_half_dt_topping_sum_exactly(): void
    {
        $p = $this->seedShopTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Ramen',
            'slug' => 'ramen-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 50_000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => true],
                'styles' => [
                    ['id' => 'tantan', 'name' => 'Tantan', 'price_minor' => 12_500],
                ],
                'toppings' => [
                    ['id' => 'extra_egg', 'name' => 'Egg', 'price_delta_minor' => 500],
                ],
            ],
        ]);

        $expected = 12_500 + 500;

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], [
            $this->line(
                (int) $item->id,
                1,
                1,
                1,
                'tantan',
                [['id' => 'extra_egg', 'name' => 'Egg', 'priceDeltaMinor' => 1]]
            ),
        ], $this->idemKey());

        $result = $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
        $line = OrderLine::query()->where('order_id', $result->posOrderId)->sole();

        $this->assertSame($expected, (int) $line->unit_price_minor);
        $this->assertSame($expected, (int) $line->line_total_minor);
    }

    public function test_rejects_unknown_numeric_menu_item_id(): void
    {
        $p = $this->seedShopTable();
        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], [
            $this->line(99_999_999, 100, 100),
        ], $this->idemKey());

        $this->expectException(GuestOrderValidationException::class);
        $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
    }

    public function test_rejects_unknown_topping_id(): void
    {
        $p = $this->seedShopTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Bowl',
            'slug' => 'bowl-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 8_000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => false],
                'styles' => [],
                'toppings' => [
                    ['id' => 'valid', 'name' => 'Valid', 'price_delta_minor' => 100],
                ],
            ],
        ]);

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], [
            $this->line(
                (int) $item->id,
                8_000,
                8_000,
                1,
                null,
                [['id' => 'not-in-master', 'name' => 'X', 'priceDeltaMinor' => 0]]
            ),
        ], $this->idemKey());

        $this->expectException(GuestOrderValidationException::class);
        $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
    }

    public function test_rejects_inactive_menu_item(): void
    {
        $p = $this->seedShopTable();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'SoldOut',
            'slug' => 'sold-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 3_000,
            'sort_order' => 0,
            'is_active' => false,
        ]);

        $action = app(SubmitGuestOrderAction::class);
        $pl = $this->payload($p['shop'], $p['table'], [
            $this->line((int) $item->id, 3_000, 3_000),
        ], $this->idemKey());

        $this->expectException(GuestOrderValidationException::class);
        $this->expectExceptionMessage(__('guest.item_unavailable'));
        $action->execute((string) $p['shop']->slug, (string) $p['table']->qr_token, $pl);
    }
}
