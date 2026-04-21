<?php

namespace App\Events\Kds;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Bistro 最適化: KDS への「即時更新ベル」イベント。
 *
 * - ペイロードは shop_id と action のみ（エンティティ詳細は載せない）。
 * - 真実の源泉は KDS 側の `wire:poll.10s` による DB プル。
 * - `ShouldBroadcastNow` で sync キューを介さずに直接ブロードキャストする
 *   （送信は `KdsBroadcastService` 内の `dispatch()->afterResponse()` で
 *   FPM 応答後に行う前提）。
 */
class OrderConfirmedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $shopId,
    ) {}

    public function broadcastAs(): string
    {
        return 'kds.orders.confirmed';
    }

    /**
     * 公開チャンネル（auth 不要）。
     * Echo クライアントは PIN セッションの KDS 端末から
     * `Echo.channel('pos.shop.{shopId}')` で購読する。
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new Channel('pos.shop.'.$this->shopId)];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'shop_id' => $this->shopId,
            'action' => 'order_confirmed',
        ];
    }
}
