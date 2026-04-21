<?php

namespace App\Exceptions\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use RuntimeException;

/**
 * Thrown by {@see FinalizeTableSettlementAction} when the
 * cashier attempts to settle with tendered < finalTotal for non-card
 * payment methods (e.g. cash). Card / voucher methods bypass this check
 * because the final amount is charged directly by the terminal.
 */
final class InsufficientTenderException extends RuntimeException
{
    public function __construct(
        public int $tableSessionId,
        public int $finalTotalMinor,
        public int $tenderedMinor,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('rad_table.insufficient_tender'));
    }
}
