<?php

namespace Tests\Unit;

use App\Services\BistronipponOrdersRecettesService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BistronipponOrdersRecettesServiceTest extends TestCase
{
    public function test_end_date_is_parsed_in_processing_timezone_not_app_timezone(): void
    {
        Config::set('app.timezone', 'UTC');
        Config::set('daily_close.orders_api.url', 'https://example.test/api/orders');
        Config::set('daily_close.orders_api.processing_timezone', 'Europe/Paris');
        Config::set('daily_close.orders_api.use_legacy_strtotime', false);

        // 店舗ローカル 17:00 = まだ Midi（18:00 まで）。文字列に TZ が無いので Paris として解釈すべき。
        Http::fake([
            'https://example.test/*' => Http::response([
                ['end_date' => '2026-04-01 17:00:00', 'total' => 100],
            ], 200),
        ]);

        $svc = app(BistronipponOrdersRecettesService::class);
        $r = $svc->fetchLunchDinnerTotals('2026-04-01');

        $this->assertSame(100.0, $r['lunch']);
        $this->assertSame(0.0, $r['dinner']);
        $this->assertSame(100.0, $r['journal']);
    }

    public function test_lunch_includes_end_at_18_00_00_dinner_starts_18_00_01(): void
    {
        Config::set('daily_close.orders_api.url', 'https://example.test/api/orders');
        Config::set('daily_close.orders_api.processing_timezone', 'Europe/Paris');
        Config::set('daily_close.orders_api.use_legacy_strtotime', false);

        Http::fake([
            'https://example.test/*' => Http::response([
                ['end_date' => '2026-04-01 18:00:00', 'total' => 3],
                ['end_date' => '2026-04-01 18:00:01', 'total' => 7],
            ], 200),
        ]);

        $svc = app(BistronipponOrdersRecettesService::class);
        $r = $svc->fetchLunchDinnerTotals('2026-04-01');

        $this->assertSame(3.0, $r['lunch']);
        $this->assertSame(7.0, $r['dinner']);
        $this->assertSame(10.0, $r['journal']);
    }
}
