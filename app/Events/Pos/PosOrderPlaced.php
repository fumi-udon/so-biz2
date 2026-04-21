<?php

namespace App\Events\Pos;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * V4: docs/technical_contract_v4.md §3.1–3.3
 */
class PosOrderPlaced implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public int $shopId,
        public int $restaurantTableId,
        public int $tableSessionId,
        public int $posOrderId,
    ) {}

    public function broadcastAs(): string
    {
        return 'pos.order.placed';
    }

    /**
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('pos.shop.'.$this->shopId)];
    }

    /**
     * @return array<string, int>
     */
    public function broadcastWith(): array
    {
        return [
            'shop_id' => $this->shopId,
            'restaurant_table_id' => $this->restaurantTableId,
            'table_session_id' => $this->tableSessionId,
            'pos_order_id' => $this->posOrderId,
        ];
    }
}
