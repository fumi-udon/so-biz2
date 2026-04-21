<?php

namespace App\Observers;

use App\Enums\OrderStatus;
use App\Models\OrderLine;
use RuntimeException;

/**
 * Prevents accidental mutation of historical order lines after staff confirmation / payment.
 * KDS のチケット遷移（status / line_revision）は許可する（technical_contract_v4.md §11）。
 */
class OrderLineObserver
{
    /**
     * @var list<string>
     */
    private const KDS_TICKET_FIELDS = ['status', 'line_revision', 'updated_at', 'line_discount_minor'];

    public function updating(OrderLine $orderLine): void
    {
        $this->assertMutable($orderLine);
    }

    public function deleting(OrderLine $orderLine): void
    {
        $this->assertMutable($orderLine);
    }

    private function assertMutable(OrderLine $orderLine): void
    {
        $order = $orderLine->order;
        if ($order === null) {
            return;
        }

        if (in_array($order->status, [OrderStatus::Draft, OrderStatus::Placed], true)) {
            return;
        }

        $dirty = array_keys($orderLine->getDirty());
        if ($dirty !== [] && empty(array_diff($dirty, self::KDS_TICKET_FIELDS))) {
            return;
        }

        throw new RuntimeException('Cannot modify or delete order lines for orders that are not in draft or placed state.');
    }
}
