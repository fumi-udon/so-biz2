<?php

namespace App\Exceptions\Pos;

use App\Actions\Pos\FinalizeTableSettlementAction;
use RuntimeException;

/**
 * Thrown when {@see FinalizeTableSettlementAction} is called
 * against a session that already has a row in `table_session_settlements`,
 * or whose status is no longer Active. The unique constraint on
 * `table_session_settlements.table_session_id` provides the database-level
 * belt-and-braces; this exception is the friendly application layer.
 */
final class SessionAlreadySettledException extends RuntimeException
{
    public function __construct(
        public int $tableSessionId,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('rad_table.session_already_settled'));
    }
}
