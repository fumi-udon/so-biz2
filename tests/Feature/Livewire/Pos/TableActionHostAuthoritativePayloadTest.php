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
        $scripts = is_array($effects) ? ($effects['scripts'] ?? []) : [];
        if (! is_array($scripts)) {
            return '';
        }
        $parts = [];
        foreach ($scripts as $chunk) {
            if (is_string($chunk)) {
                $parts[] = $chunk;
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
    }
}
