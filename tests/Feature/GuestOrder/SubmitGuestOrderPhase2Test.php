<?php

namespace Tests\Feature\GuestOrder;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Enums\OrderLineStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

class SubmitGuestOrderPhase2Test extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    /**
     * @return array{0: Shop, 1: MenuItem, 2: RestaurantTable}
     */
    private function seedMinimalGuestMenu(): array
    {
        $shop = Shop::query()->create([
            'name' => 'Test Shop',
            'slug' => 'test-shop',
            'is_active' => true,
        ]);
        $cat = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Food',
            'slug' => 'food',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $cat->id,
            'name' => 'Gyoza',
            'kitchen_name' => 'Gyoza',
            'slug' => 'gyoza',
            'from_price_minor' => 12000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'T1',
            'qr_token' => 'test-qr-token-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $this->createActiveTableSession($shop, $table);

        return [$shop, $item, $table];
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(string $tenantSlug, string $tableToken, int $itemId, string $idempotencyKey): array
    {
        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'sess-test',
            'context' => [
                'tenantSlug' => $tenantSlug,
                'tableToken' => $tableToken,
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
                'titleSnapshot' => 'Gyoza',
                'kitchenNameSnapshot' => 'Gyoza',
                'styleId' => null,
                'styleNameSnapshot' => null,
                'stylePriceMinor' => 12000,
                'toppingSnapshots' => [],
                'unitLineTotalMinor' => 12000,
                'qty' => 1,
                'lineTotalMinor' => 12000,
                'note' => '',
            ]],
            'totals' => [
                'currency' => 'TND',
                'priceDivisor' => 1000,
                'subtotalMinor' => 12000,
            ],
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    public function test_idempotent_submit_returns_same_pos_order(): void
    {
        [$shop, $item, $table] = $this->seedMinimalGuestMenu();
        $action = app(SubmitGuestOrderAction::class);
        $key = 'idem-test-'.bin2hex(random_bytes(8));
        $payload = $this->basePayload((string) $shop->slug, (string) $table->qr_token, (int) $item->id, $key);

        $r1 = $action->execute((string) $shop->slug, (string) $table->qr_token, $payload);
        $r2 = $action->execute((string) $shop->slug, (string) $table->qr_token, $payload);

        $this->assertSame($r1->posOrderId, $r2->posOrderId);
        $this->assertSame(1, PosOrder::query()->count());
    }

    public function test_order_line_update_throws_when_pos_order_is_confirmed(): void
    {
        [$shop, $item, $table] = $this->seedMinimalGuestMenu();
        TableSession::query()->where('restaurant_table_id', $table->id)->delete();
        $session = TableSession::query()->create([
            'shop_id' => $shop->id,
            'restaurant_table_id' => $table->id,
            'token' => Str::lower(Str::random(48)),
            'status' => TableSessionStatus::Active,
            'opened_at' => now(),
            'closed_at' => null,
        ]);
        $order = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Confirmed,
            'total_price_minor' => 12000,
            'placed_at' => now(),
        ]);
        $line = OrderLine::query()->create([
            'order_id' => $order->id,
            'menu_item_id' => $item->id,
            'qty' => 1,
            'unit_price_minor' => 12000,
            'line_total_minor' => 12000,
            'snapshot_name' => 'Gyoza',
            'snapshot_kitchen_name' => 'Gyoza',
            'snapshot_options_payload' => [],
            'status' => OrderLineStatus::Placed,
        ]);

        $this->expectException(RuntimeException::class);
        $line->update(['qty' => 2]);
    }
}
