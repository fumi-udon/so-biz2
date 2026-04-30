<?php

namespace Tests\Feature\Pos2;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Actions\Pos\AddPosOrderFromStaffAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Enums\OrderLineStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Shop;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

/**
 * POS V2 GET /pos2/api/sessions/{id}/orders: ゲスト注文と Add to Table（スタッフ）が同一 JSON に載り、
 * 行ごとに is_unsent（KDS 前）が立ち、Recu 後に偽になること。
 */
final class Pos2SessionOrdersMergedGuestAndStaffTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * @return array{0: MenuItem, 1: MenuItem}
     */
    private function twoMenuItems(Shop $shop): array
    {
        $category = MenuCategory::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'Pos2 Merge Cat'],
            [
                'slug' => 'pos2-merge-cat-'.Str::lower(Str::random(4)),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $a = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Merge Item Guest',
            'kitchen_name' => 'Merge Item Guest',
            'slug' => 'merge-guest-'.Str::lower(Str::random(4)),
            'from_price_minor' => 1_000,
            'sort_order' => 1,
            'is_active' => true,
            'options_payload' => ['styles' => [], 'toppings' => [], 'rules' => ['style_required' => false]],
        ]);

        $b = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Merge Item Staff',
            'kitchen_name' => 'Merge Item Staff',
            'slug' => 'merge-staff-'.Str::lower(Str::random(4)),
            'from_price_minor' => 2_000,
            'sort_order' => 2,
            'is_active' => true,
            'options_payload' => ['styles' => [], 'toppings' => [], 'rules' => ['style_required' => false]],
        ]);

        return [$a, $b];
    }

    /**
     * @return array<string, mixed>
     */
    private function guestPayload(Shop $shop, string $tableQrToken, int $menuItemId, string $idempotencyKey): array
    {
        return [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => $idempotencyKey,
            'clientSessionId' => 'pos2-merge-test',
            'context' => [
                'tenantSlug' => (string) $shop->slug,
                'tableToken' => $tableQrToken,
                'locale' => 'en',
            ],
            'catalogFingerprint' => ['currency' => 'TND', 'priceDivisor' => 1000],
            'lines' => [[
                'lineId' => (string) Str::uuid(),
                'mergeKey' => $menuItemId.'|k',
                'itemId' => (string) $menuItemId,
                'titleSnapshot' => 'GuestLine',
                'kitchenNameSnapshot' => 'GuestLine',
                'styleId' => null,
                'styleNameSnapshot' => null,
                'stylePriceMinor' => 0,
                'toppingSnapshots' => [],
                'unitLineTotalMinor' => 0,
                'qty' => 1,
                'lineTotalMinor' => 0,
                'note' => '',
            ]],
            'totals' => ['currency' => 'TND', 'priceDivisor' => 1000, 'subtotalMinor' => 0],
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    public function test_session_orders_lists_guest_and_staff_lines_with_is_unsent_until_recu(): void
    {
        $shop = $this->makeShop('pos2-merge-orders');
        $table = $this->makeCustomerTable($shop, 11);
        $session = $this->openActiveSession($shop, $table);
        [$itemGuest, $itemStaff] = $this->twoMenuItems($shop);

        app(SubmitGuestOrderAction::class)->execute(
            (string) $shop->slug,
            (string) $table->qr_token,
            $this->guestPayload($shop, (string) $table->qr_token, (int) $itemGuest->id, 'idem-guest-'.bin2hex(random_bytes(8))),
        );

        app(AddPosOrderFromStaffAction::class)->execute(
            (int) $shop->id,
            (int) $table->id,
            (int) $itemStaff->id,
            1,
            null,
            [],
            '',
        );

        $get1 = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->getJson('/pos2/api/sessions/'.$session->id.'/orders');

        $get1->assertOk();
        $get1->assertJsonPath('has_unacked_placed', true);
        $orders = $get1->json('orders');
        $this->assertIsArray($orders);
        $this->assertCount(2, $orders);

        $orderedBy = [];
        $allUnsent = [];
        foreach ($orders as $o) {
            $this->assertArrayHasKey('lines', $o);
            foreach ($o['lines'] as $ln) {
                $orderedBy[] = $o['ordered_by'];
                $allUnsent[] = $ln['is_unsent'];
                $this->assertSame('placed', $ln['line_status']);
                $this->assertTrue($ln['is_unsent']);
            }
        }
        sort($orderedBy);
        $this->assertSame(['guest', 'staff'], $orderedBy);
        $this->assertSame([true, true], $allUnsent);

        $revision = (int) $get1->json('session_revision');
        app(RecuPlacedOrdersForSessionAction::class)->execute((int) $shop->id, (int) $session->id, $revision);

        $get2 = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->getJson('/pos2/api/sessions/'.$session->id.'/orders');

        $get2->assertOk();
        $get2->assertJsonPath('has_unacked_placed', false);
        foreach ($get2->json('orders') as $o) {
            foreach ($o['lines'] as $ln) {
                $this->assertSame(OrderLineStatus::Confirmed->value, $ln['line_status']);
                $this->assertFalse($ln['is_unsent']);
            }
        }
    }
}
