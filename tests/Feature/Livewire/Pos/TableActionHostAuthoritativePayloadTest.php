<?php

/**
 * TableActionHost — authoritative browser payload (Phase 2-A).
 *
 * ## MySQL / DB 前提（このファイルを実行する手順）
 *
 * - `phpunit.xml` は `.env.testing` の DB 設定を参照する想定（コメント参照）。
 * - **ホストで実行する場合:** `pdo_mysql` が有効な PHP で、`.env.testing` の `DB_HOST` 等が
 *   起動中の MySQL（例: `soya_biz2_test` スキーマ）を指すこと。
 * - **Laravel Sail 推奨:** コンテナ内の PHP で実行するとドライバ・ホスト名が揃いやすい。
 *   例: `./vendor/bin/sail artisan test tests/Feature/Livewire/Pos/TableActionHostAuthoritativePayloadTest.php`
 * - `could not find driver` / `getaddrinfo for mysql failed` のときは、上記のいずれかで接続を直してから再実行。
 */

namespace Tests\Feature\Livewire\Pos;

use App\Enums\OrderStatus;
use App\Livewire\Pos\TableActionHost;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\OrderLine;
use App\Models\PosOrder;
use App\Models\TableSessionSettlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class TableActionHostAuthoritativePayloadTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    private static function scriptsBlob(mixed $effects): string
    {
        if (! is_array($effects)) {
            return '';
        }

        $scripts = $effects['scripts'] ?? [];
        $js = $effects['js'] ?? [];
        $xjs = $effects['xjs'] ?? [];
        $chunks = [];
        if (is_array($scripts)) {
            $chunks = array_merge($chunks, $scripts);
        }
        if (is_array($js)) {
            $chunks = array_merge($chunks, $js);
        }
        if (is_array($xjs)) {
            $chunks = array_merge($chunks, $xjs);
        }

        $parts = [];
        foreach ($chunks as $chunk) {
            if (is_string($chunk)) {
                $parts[] = $chunk;
                continue;
            }
            if (is_array($chunk)) {
                $expression = $chunk['expression'] ?? $chunk['script'] ?? null;
                if (is_string($expression) && $expression !== '') {
                    $parts[] = $expression;
                }
            }
        }

        return implode("\n", $parts);
    }

    public function test_load_order_details_emits_ui_sync_then_authoritative_in_one_script(): void
    {
        $shop = $this->makeShop('auth-payload');
        $table = $this->makeCustomerTable($shop, 12);
        $session = $this->openActiveSession($shop, $table);
        $order = $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::now());
        $line = $this->addLineAt($shop, $order, Carbon::now());

        $operator = $this->makeOperator('auth-payload');
        $this->actingAs($operator);

        $c = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->set('activeRestaurantTableId', (int) $table->id)
            ->call('loadSessionData', (int) $session->id);

        $blob = self::scriptsBlob($c->effects);
        $this->assertNotSame('', $blob);
        $this->assertStringContainsString('pos-action-host-ui-sync', $blob);
        $this->assertStringContainsString('pos-action-host-authoritative', $blob);
        $this->assertLessThan(
            strpos($blob, 'pos-action-host-authoritative'),
            strpos($blob, 'pos-action-host-ui-sync'),
        );
        $this->assertStringContainsString('shopId', $blob);
        $this->assertStringContainsString('tableSessionId', $blob);
        $this->assertStringContainsString((string) $line->id, $blob);
        $this->assertStringContainsString('is_unsent', $blob);
    }

    public function test_open_empty_table_emits_authoritative_with_empty_lines(): void
    {
        $shop = $this->makeShop('auth-empty');
        $this->makeCustomerTable($shop, 11);
        $operator = $this->makeOperator('auth-empty');
        $this->actingAs($operator);

        $c = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', 11, null);

        $blob = self::scriptsBlob($c->effects);
        $this->assertStringContainsString('pos-action-host-ui-sync', $blob);
        $this->assertStringContainsString('pos-action-host-authoritative', $blob);
        $this->assertMatchesRegularExpression(
            '/pos-action-host-ui-sync[\s\S]*pos-action-host-authoritative/',
            $blob,
        );
        $this->assertStringContainsString('lines', $blob);
        $this->assertStringContainsString('tableSessionId', $blob);
        $this->assertMatchesRegularExpression('/lines[^\]]*\[\]/', $blob);
    }

    public function test_on_action_host_opened_with_session_emits_authoritative_with_line_ids(): void
    {
        $shop = $this->makeShop('auth-open-session');
        $table = $this->makeCustomerTable($shop, 13);
        $session = $this->openActiveSession($shop, $table);
        $order = $this->placeOrderAt($shop, $session, OrderStatus::Confirmed, Carbon::now());
        $line = $this->addLineAt($shop, $order, Carbon::now());

        $operator = $this->makeOperator('auth-open-session');
        $this->actingAs($operator);

        $c = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->call('onActionHostOpened', (int) $table->id, (int) $session->id);

        $blob = self::scriptsBlob($c->effects);
        $this->assertStringContainsString('pos-action-host-authoritative', $blob);
        $this->assertStringContainsString((string) $line->id, $blob);
        $this->assertStringContainsString('shopId', $blob);
        $this->assertStringContainsString('tableSessionId', $blob);
        $this->assertStringContainsString('is_unsent', $blob);
    }

    public function test_bulk_add_drafts_only_keeps_existing_lines_and_adds_all_drafts(): void
    {
        $shop = $this->makeShop('auth-bulk-add');
        $table = $this->makeCustomerTable($shop, 14);
        $session = $this->openActiveSession($shop, $table);

        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Bulk Add Category',
            'slug' => 'bulk-add-category',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $itemA = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Item A',
            'slug' => 'bulk-item-a',
            'kitchen_name' => 'Item A',
            'from_price_minor' => 1200,
            'sort_order' => 1,
            'is_active' => true,
            'options_payload' => [
                'rules' => [
                    'style_required' => false,
                ],
                'styles' => [
                    ['id' => 's1', 'name' => 'Style 1', 'price_minor' => 1200],
                ],
                'toppings' => [
                    ['id' => 't1', 'name' => 'Top 1', 'price_delta_minor' => 100],
                ],
            ],
        ]);
        $itemB = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Item B',
            'slug' => 'bulk-item-b',
            'kitchen_name' => 'Item B',
            'from_price_minor' => 900,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $itemC = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Item C',
            'slug' => 'bulk-item-c',
            'kitchen_name' => 'Item C',
            'from_price_minor' => 800,
            'sort_order' => 3,
            'is_active' => true,
        ]);

        $existingOrder = PosOrder::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $session->id,
            'status' => OrderStatus::Placed,
            'total_price_minor' => 1200,
            'rounding_adjustment_minor' => 0,
            'placed_at' => now(),
        ]);
        $existingLine = OrderLine::query()->create([
            'shop_id' => $shop->id,
            'order_id' => $existingOrder->id,
            'menu_item_id' => $itemA->id,
            'qty' => 1,
            'unit_price_minor' => 1200,
            'line_total_minor' => 1200,
            'snapshot_name' => 'Item A',
            'snapshot_kitchen_name' => 'Item A',
            'snapshot_options_payload' => ['style' => null, 'toppings' => [], 'note' => ''],
            'status' => \App\Enums\OrderLineStatus::Placed,
        ]);

        $operator = $this->makeOperator('auth-bulk-add');
        $this->actingAs($operator);

        $component = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->set('activeRestaurantTableId', (int) $table->id)
            ->call('loadSessionData', (int) $session->id)
            ->call('bulkAddDraftsOnly', [
                [
                    'menu_item_id' => (int) $itemA->id,
                    'qty' => 1,
                    'styleId' => 's1',
                    'toppings' => ['t1'],
                    'note' => 'note-a',
                ],
                [
                    'menu_item_id' => (int) $itemB->id,
                    'qty' => 1,
                    'styleId' => null,
                    'toppings' => [],
                    'note' => '',
                ],
                [
                    'menu_item_id' => (int) $itemC->id,
                    'qty' => 1,
                    'styleId' => null,
                    'toppings' => [],
                    'note' => '',
                ],
            ]);

        $lineNames = OrderLine::query()
            ->whereHas('order', function ($q) use ($session): void {
                $q->where('table_session_id', (int) $session->id);
            })
            ->orderBy('id')
            ->pluck('snapshot_name')
            ->all();

        $this->assertSame(4, count($lineNames));
        $this->assertSame('Item A', $lineNames[0]);
        $this->assertContains('Item B', $lineNames);
        $this->assertContains('Item C', $lineNames);

        $blob = self::scriptsBlob($component->effects);
        $this->assertStringContainsString((string) $existingLine->id, $blob);
        $this->assertStringContainsString('Item A', $blob);
        $this->assertStringContainsString('Item B', $blob);
        $this->assertStringContainsString('Item C', $blob);
    }

    public function test_bulk_add_drafts_resyncs_to_latest_active_session_when_selected_session_is_settled(): void
    {
        $shop = $this->makeShop('auth-resync-session');
        $table = $this->makeCustomerTable($shop, 15);
        $oldSession = $this->openActiveSession($shop, $table);

        TableSessionSettlement::query()->create([
            'shop_id' => $shop->id,
            'table_session_id' => $oldSession->id,
            'order_subtotal_minor' => 1000,
            'order_discount_applied_minor' => 0,
            'total_before_rounding_minor' => 1000,
            'rounding_adjustment_minor' => 0,
            'final_total_minor' => 1000,
            'tendered_minor' => 1000,
            'change_minor' => 0,
            'payment_method' => 'cash',
            'session_revision_at_settle' => 0,
            'settled_by_user_id' => null,
            'settled_at' => now(),
            'print_bypassed' => false,
            'bypass_reason' => null,
            'bypassed_by_user_id' => null,
        ]);

        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Resync Category',
            'slug' => 'resync-category',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Resync Item',
            'slug' => 'resync-item',
            'kitchen_name' => 'Resync Item',
            'from_price_minor' => 1000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $operator = $this->makeOperator('auth-resync-session');
        $this->actingAs($operator);

        $component = Livewire::test(TableActionHost::class, ['shopId' => $shop->id])
            ->set('activeRestaurantTableId', (int) $table->id)
            ->set('activeTableSessionId', (int) $oldSession->id)
            ->set('isOrdersLoaded', true)
            ->call('bulkAddDraftsOnly', [[
                'menu_item_id' => (int) $item->id,
                'qty' => 1,
                'styleId' => null,
                'toppings' => [],
                'note' => '',
            ]]);

        $latestActiveSessionId = (int) \App\Models\TableSession::query()
            ->where('shop_id', $shop->id)
            ->where('restaurant_table_id', (int) $table->id)
            ->where('status', \App\Enums\TableSessionStatus::Active)
            ->orderByDesc('id')
            ->value('id');

        $this->assertNotSame((int) $oldSession->id, $latestActiveSessionId);
        $component->assertSet('activeTableSessionId', $latestActiveSessionId);

        $lineCount = OrderLine::query()
            ->whereHas('order', function ($q) use ($latestActiveSessionId): void {
                $q->where('table_session_id', $latestActiveSessionId);
            })
            ->count();
        $this->assertSame(1, $lineCount);
    }
}
