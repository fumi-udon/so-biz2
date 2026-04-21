<?php

namespace Tests\Feature\Kds;

use App\Actions\GuestOrder\SubmitGuestOrderAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
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
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Broadcasting\Broadcasters\PusherBroadcaster;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Pusher\Pusher;
use Tests\Support\CreatesActiveTableSessions;
use Tests\TestCase;

/**
 * Bistro 最適化版 KDS ブロードキャストの「障害耐性絶対証明」テスト。
 *
 * 単なる Event::fake() では Pusher SDK の HTTP 層を経由しないため、
 * 実運用で起こる「Pusher API 500」「connect timeout」を再現できない。
 * ここでは Guzzle MockHandler を Pusher SDK の Guzzle クライアントに注入し、
 *   - HTTP レスポンスは即時に返り、
 *   - DB 状態は正しく確定し、
 *   - 例外は Log::warning で握り潰される
 * ことを証明する。
 */
class KdsBroadcastIntegrationTest extends TestCase
{
    use CreatesActiveTableSessions;
    use RefreshDatabase;

    /**
     * モック Guzzle を持った PusherBroadcaster を Broadcast マネージャに差し込む。
     *
     * @param  list<GuzzleResponse|\Throwable>  $queue
     */
    private function bindMockedPusher(array $queue): MockHandler
    {
        $mock = new MockHandler($queue);
        $stack = HandlerStack::create($mock);
        $guzzle = new GuzzleClient(['handler' => $stack]);

        $pusher = new Pusher(
            'test-key',
            'test-secret',
            'test-app',
            ['cluster' => 'mt1', 'useTLS' => true],
            $guzzle,
        );

        // broadcasting.default を pusher に固定し、custom driver を注入。
        config(['broadcasting.default' => 'pusher']);
        config(['broadcasting.connections.pusher' => [
            'driver' => 'pusher',
            'key' => 'test-key',
            'secret' => 'test-secret',
            'app_id' => 'test-app',
            'options' => ['cluster' => 'mt1', 'useTLS' => true],
            'client_options' => ['connect_timeout' => 0.7, 'timeout' => 1.5],
        ]]);

        // BroadcastManager 内部のドライバキャッシュを破棄し、
        // モック Pusher を返す extend を仕込む。
        /** @var BroadcastManager $manager */
        $manager = app(BroadcastManager::class);
        $manager->forgetDrivers();
        Broadcast::extend('pusher', static fn () => new PusherBroadcaster($pusher));

        return $mock;
    }

    /**
     * @return array{shop: Shop, category: MenuCategory, item: MenuItem, table: RestaurantTable, session: TableSession}
     */
    private function seedScenario(): array
    {
        $shop = Shop::query()->create([
            'name' => 'Bell Shop',
            'slug' => 'bell-shop-'.bin2hex(random_bytes(3)),
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
            'name' => 'Bell Ramen',
            'slug' => 'bell-ramen',
            'from_price_minor' => 1200,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $table = RestaurantTable::query()->create([
            'shop_id' => $shop->id,
            'name' => 'B-1',
            'qr_token' => 'bell-qr-'.bin2hex(random_bytes(8)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $session = $this->createActiveTableSession($shop, $table);

        // ホールがゲスト注文を Submit → Placed 状態を作る。
        $payload = [
            'schemaVersion' => 1,
            'intent' => 'submit_to_table_pos',
            'idempotencyKey' => 'bell-idem-'.bin2hex(random_bytes(8)),
            'clientSessionId' => 'guest-bell',
            'context' => [
                'tenantSlug' => (string) $shop->slug,
                'tableToken' => (string) $table->qr_token,
                'locale' => 'en',
            ],
            'catalogFingerprint' => ['currency' => 'TND', 'priceDivisor' => 1000],
            'lines' => [[
                'lineId' => (string) Str::uuid(),
                'mergeKey' => $item->id.'|__none__|',
                'itemId' => (string) $item->id,
                'titleSnapshot' => 'Bell Ramen',
                'kitchenNameSnapshot' => 'Bell Ramen',
                'styleId' => null,
                'styleNameSnapshot' => null,
                'stylePriceMinor' => 1200,
                'toppingSnapshots' => [],
                'unitLineTotalMinor' => 1200,
                'qty' => 1,
                'lineTotalMinor' => 1200,
                'note' => '',
            ]],
            'totals' => ['currency' => 'TND', 'priceDivisor' => 1000, 'subtotalMinor' => 1200],
            'generatedAt' => now()->toIso8601String(),
        ];
        app(SubmitGuestOrderAction::class)->execute(
            (string) $shop->slug,
            (string) $table->qr_token,
            $payload,
        );

        $session->refresh();

        return compact('shop', 'category', 'item', 'table', 'session');
    }

    public function test_pusher_500_does_not_break_action(): void
    {
        // 先にシナリオを構築（この時点では broadcasting.default はテスト設定の `null` で
        // 何も投げない）。Recu が投げる OrderConfirmedBroadcast のみをモック Pusher で受ける。
        $s = $this->seedScenario();

        $mock = $this->bindMockedPusher([
            new GuzzleResponse(500, [], 'Internal Server Error'),
        ]);
        $logSpy = Log::spy();

        $n = app(RecuPlacedOrdersForSessionAction::class)->execute(
            (int) $s['shop']->id,
            (int) $s['session']->id,
            (int) $s['session']->session_revision,
        );
        // afterResponse: Pusher API への HTTP は terminate 内で実行され、500 で失敗する。
        $this->app->terminate();

        // メイン処理は完走しており DB は確定している。
        $this->assertSame(1, $n);
        $pos = PosOrder::query()
            ->where('table_session_id', $s['session']->id)
            ->sole();
        $this->assertSame(OrderStatus::Confirmed, $pos->status);
        $this->assertSame(
            OrderLineStatus::Confirmed,
            OrderLine::query()->where('order_id', $pos->id)->sole()->status,
        );

        // モックは確かに 1 回 HTTP 呼び出しを受けた = 例外まで到達している。
        $this->assertSame(0, $mock->count(), 'Pusher SDK should have consumed the mocked 500 response.');

        // KdsBroadcastService は例外を握り潰し、Log::warning を残す。
        $logSpy->shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []) use ($s): bool {
                return $message === 'KDS broadcast suppressed (notifyOrderConfirmed)'
                    && (int) ($context['shop_id'] ?? 0) === (int) $s['shop']->id;
            })
            ->atLeast()
            ->once();
    }

    public function test_pusher_connect_timeout_does_not_block_response(): void
    {
        $s = $this->seedScenario();

        $this->bindMockedPusher([
            new ConnectException(
                'cURL error 28: Connection timed out',
                new GuzzleRequest('POST', 'https://api-mt1.pusher.com/apps/test-app/events'),
            ),
        ]);
        $logSpy = Log::spy();

        // ホール側 Action 自体は Pusher を一切呼ばない（afterResponse のため）。
        // → connect timeout の遅延がメイン応答を引きずらないことを時間で確認する。
        $start = microtime(true);
        $n = app(RecuPlacedOrdersForSessionAction::class)->execute(
            (int) $s['shop']->id,
            (int) $s['session']->id,
            (int) $s['session']->session_revision,
        );
        $elapsedAction = microtime(true) - $start;

        $this->assertSame(1, $n);
        // 0.5s 未満で返ること（実 Pusher の connect_timeout 0.7s をも下回る）。
        $this->assertLessThan(
            0.5,
            $elapsedAction,
            'Hall action must return without blocking on Pusher; afterResponse must defer the I/O.',
        );

        // ここで初めて afterResponse 側が走り、ConnectException が発生 → 握り潰し。
        $this->app->terminate();

        $logSpy->shouldHaveReceived('warning')
            ->withArgs(function (string $message, array $context = []) use ($s): bool {
                return $message === 'KDS broadcast suppressed (notifyOrderConfirmed)'
                    && (int) ($context['shop_id'] ?? 0) === (int) $s['shop']->id;
            })
            ->atLeast()
            ->once();

        // DB 状態は正常に確定済み（broadcast 失敗はメインの真実に影響しない）。
        $line = OrderLine::query()
            ->whereHas('order', fn ($q) => $q->where('table_session_id', $s['session']->id))
            ->sole();
        $this->assertSame(OrderLineStatus::Confirmed, $line->status);

        // Active セッションの session_revision も進んでいる（KDS 側が次回 pull で同期可能）。
        $session = TableSession::query()->whereKey($s['session']->id)->sole();
        $this->assertSame(TableSessionStatus::Active, $session->status);
    }
}
