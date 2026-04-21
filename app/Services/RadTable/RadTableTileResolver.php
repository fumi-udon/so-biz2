<?php

namespace App\Services\RadTable;

use App\Enums\OrderStatus;
use App\Enums\RadTableTileColor;
use App\Enums\TableSessionStatus;
use App\Models\PosOrder;
use App\Models\TableSession;
use Illuminate\Support\Collection;

final class RadTableTileResolver
{
    /**
     * Priority: Red (any placed) > White (no active session) handled by caller —
     * here session is null for vacant, or active session with posOrders loaded.
     */
    public function resolveColor(?TableSession $session): RadTableTileColor
    {
        if ($session === null || $session->status !== TableSessionStatus::Active) {
            return RadTableTileColor::White;
        }

        $orders = $this->relevantOrders($session);

        if ($orders->contains(fn (PosOrder $o): bool => $o->status === OrderStatus::Placed)) {
            return RadTableTileColor::Red;
        }

        if ($orders->isEmpty()) {
            return RadTableTileColor::Green;
        }

        $allConfirmedOrPaid = $orders->every(fn (PosOrder $o): bool => in_array($o->status, [
            OrderStatus::Confirmed,
            OrderStatus::Paid,
        ], true));

        if (! $allConfirmedOrPaid) {
            return RadTableTileColor::Red;
        }

        $maxUpdated = $orders->max('updated_at');
        $maxTs = $maxUpdated?->getTimestamp() ?? 0;

        $printedAt = $session->last_addition_printed_at;
        $printedTs = $printedAt?->getTimestamp();

        if ($printedTs !== null && $printedTs >= $maxTs) {
            return RadTableTileColor::Yellow;
        }

        return RadTableTileColor::Green;
    }

    /**
     * @return Collection<int, PosOrder>
     */
    public function relevantOrders(TableSession $session): Collection
    {
        return $session->posOrders->filter(
            fn (PosOrder $o): bool => $o->status !== OrderStatus::Voided,
        )->values();
    }

    public function sessionTotalMinor(?TableSession $session): int
    {
        if ($session === null) {
            return 0;
        }

        return (int) $this->relevantOrders($session)->sum('total_price_minor');
    }
}
