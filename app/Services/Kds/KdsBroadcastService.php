<?php

namespace App\Services\Kds;

use App\Events\Kds\OrderConfirmedBroadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * KDS 即時更新ベルのブロードキャスト集約点（Bistro 最適化版）。
 *
 * 背景:
 *  - OVH 共用サーバーのため `QUEUE_CONNECTION=sync` を維持する必要があり、
 *    ブロードキャストを素直に呼ぶと Pusher への HTTP I/O が
 *    ホール iPad の HTTP レスポンスをブロックする可能性がある。
 *  - そこで `dispatch(...)->afterResponse()` で FPM の応答返却後に実行する。
 *  - Pusher API の 5xx・タイムアウト等が発生してもメイン処理を巻き込まないよう
 *    例外は完全に握り潰し、`Log::warning` のみ残す。
 *
 * 真実の源泉は KDS 側の 10 秒ポーリング（`wire:poll.10s`）であり、
 * 本通知はあくまで「ベル（即時反映のトリガー）」である。
 */
final class KdsBroadcastService
{
    private const CACHE_OK_PREFIX = 'kds:broadcast:last_ok:shop:';

    private const CACHE_FAIL_PREFIX = 'kds:broadcast:last_fail:shop:';

    /**
     * Recu などのホール側確定 Action から、DB トランザクション
     * コミット後に呼び出すこと（`DB::afterCommit(fn () => ...)`）。
     */
    public function notifyOrderConfirmed(int $shopId): void
    {
        if ($shopId < 1) {
            return;
        }

        // afterResponse: Laravel は内部で Application::terminating() に登録するだけなので
        // ここから戻るのは即時。HTTP レスポンスは 1ms も遅延しない。
        // Pusher API の 5xx / connect timeout は terminate 後に発生し、
        // try-catch + Log::warning で完全に握り潰される。
        dispatch(static function () use ($shopId): void {
            try {
                broadcast(new OrderConfirmedBroadcast(shopId: $shopId));
                Cache::put(self::CACHE_OK_PREFIX.$shopId, now()->toIso8601String(), now()->addHours(6));
                Cache::forget(self::CACHE_FAIL_PREFIX.$shopId);
            } catch (Throwable $e) {
                Cache::put(self::CACHE_FAIL_PREFIX.$shopId, [
                    'at' => now()->toIso8601String(),
                    'error' => mb_substr($e->getMessage(), 0, 240),
                    'exception' => $e::class,
                ], now()->addHours(6));
                Log::warning('KDS broadcast suppressed (notifyOrderConfirmed)', [
                    'shop_id' => $shopId,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        })->afterResponse();
    }

    /**
     * @return array{last_ok_at:?string,last_fail_at:?string,last_fail_error:?string,last_fail_exception:?string}
     */
    public function recentHealthForShop(int $shopId): array
    {
        if ($shopId < 1) {
            return [
                'last_ok_at' => null,
                'last_fail_at' => null,
                'last_fail_error' => null,
                'last_fail_exception' => null,
            ];
        }

        $ok = Cache::get(self::CACHE_OK_PREFIX.$shopId);
        $fail = Cache::get(self::CACHE_FAIL_PREFIX.$shopId);
        $failArr = is_array($fail) ? $fail : [];

        return [
            'last_ok_at' => is_string($ok) ? $ok : null,
            'last_fail_at' => is_string($failArr['at'] ?? null) ? $failArr['at'] : null,
            'last_fail_error' => is_string($failArr['error'] ?? null) ? $failArr['error'] : null,
            'last_fail_exception' => is_string($failArr['exception'] ?? null) ? $failArr['exception'] : null,
        ];
    }
}
