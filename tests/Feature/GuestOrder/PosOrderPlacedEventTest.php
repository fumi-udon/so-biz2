<?php

namespace Tests\Feature\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Events\Pos\PosOrderPlaced;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\RestaurantTable;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

class PosOrderPlacedEventTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    public function test_new_submit_dispatches_pos_order_placed_with_v4_ids(): void
    {
        Event::fake([PosOrderPlaced::class]);

        $shop = Shop::query()->create(['name' => 'E', 'slug' => 'e', 'is_active' => true]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id, 'name' => 'C', 'slug' => 'c', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id, 'menu_category_id' => $cat->id, 'name' => 'I', 'slug' => 'i',
            'from_price_minor' => 1000, 'sort_order' => 0, 'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id, 'name' => 'T1', 'qr_token' => 'e-'.bin2hex(random_bytes(6)), 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->createActiveTableSession($shop, $table);
        $key = 'ev-'.bin2hex(random_bytes(8));
        $payload = [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $key,
            'clientSessionId' => 's',
            'context' => [
                'tenantSlug' => (string) $shop->slug,
                'tableToken' => (string) $table->qr_token,
                'locale' => 'en',
            ],
            'catalogFingerprint' => ['currency' => 'TND', 'priceDivisor' => 1000],
            'lines' => [[
                'lineId' => (string) Str::uuid(),
                'mergeKey' => $item->id.'|k',
                'itemId' => (string) $item->id,
                'titleSnapshot' => 'I', 'kitchenNameSnapshot' => 'I', 'styleId' => null,
                'styleNameSnapshot' => null, 'stylePriceMinor' => 0, 'toppingSnapshots' => [],
                'unitLineTotalMinor' => 0, 'qty' => 1, 'lineTotalMinor' => 0, 'note' => '',
            ]],
            'totals' => ['currency' => 'TND', 'priceDivisor' => 1000, 'subtotalMinor' => 0],
            'generatedAt' => now()->toIso8601String(),
        ];
        $r = app(SubmitGuestOrderAction::class)->execute((string) $shop->slug, (string) $table->qr_token, $payload);
        $this->assertGreaterThan(0, $r->posOrderId);

        Event::assertDispatched(PosOrderPlaced::class, function (PosOrderPlaced $e) use ($shop, $table, $r) {
            return $e->shopId === (int) $shop->id
                && $e->restaurantTableId === (int) $table->id
                && is_int($e->tableSessionId)
                && $e->posOrderId === $r->posOrderId;
        });
    }

    public function test_idempotent_replay_does_not_dispatch_again(): void
    {
        Event::fake([PosOrderPlaced::class]);
        $shop = Shop::query()->create(['name' => 'E2', 'slug' => 'e2', 'is_active' => true]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id, 'name' => 'C', 'slug' => 'c2', 'sort_order' => 0, 'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id, 'menu_category_id' => $cat->id, 'name' => 'I2', 'slug' => 'i2',
            'from_price_minor' => 1000, 'sort_order' => 0, 'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id, 'name' => 'T1', 'qr_token' => 'e2-'.bin2hex(random_bytes(6)), 'sort_order' => 0, 'is_active' => true,
        ]);
        $this->createActiveTableSession($shop, $table);
        $key = 'id-'.bin2hex(random_bytes(8));
        $base = [
            'schemaVersion' => 1, 'intent' => 'submit_to_table_pos', 'idempotencyKey' => $key, 'clientSessionId' => 's',
            'context' => ['tenantSlug' => (string) $shop->slug, 'tableToken' => (string) $table->qr_token, 'locale' => 'en'],
            'catalogFingerprint' => ['currency' => 'TND', 'priceDivisor' => 1000],
            'lines' => [[
                'lineId' => (string) Str::uuid(), 'mergeKey' => $item->id.'|k', 'itemId' => (string) $item->id,
                'titleSnapshot' => 'I2', 'kitchenNameSnapshot' => 'I2', 'styleId' => null, 'styleNameSnapshot' => null, 'stylePriceMinor' => 0,
                'toppingSnapshots' => [], 'unitLineTotalMinor' => 0, 'qty' => 1, 'lineTotalMinor' => 0, 'note' => '',
            ]],
            'totals' => ['currency' => 'TND', 'priceDivisor' => 1000, 'subtotalMinor' => 0], 'generatedAt' => now()->toIso8601String(),
        ];
        $a = app(SubmitGuestOrderAction::class);
        $a->execute((string) $shop->slug, (string) $table->qr_token, $base);
        $a->execute((string) $shop->slug, (string) $table->qr_token, $base);
        Event::assertDispatchedTimes(PosOrderPlaced::class, 1);
    }
}
