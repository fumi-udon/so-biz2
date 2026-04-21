<?php

namespace App\Exceptions\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use RuntimeException;

/**
 * Thrown by {@see FinalizeTableSettlementAction} when the
 * cashier tries to finalize a session that still has unacknowledged (Placed)
 * orders. Dedicated subclass so the Livewire layer can render a specific
 * "confirm orders first" UX rather than a generic error.
 */
final class PendingOrdersRemainException extends RuntimeException
{
    public function __construct(
        public int $tableSessionId,
        public int $pendingCount,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('rad_table.cannot_close_with_unacked'));
    }
}
