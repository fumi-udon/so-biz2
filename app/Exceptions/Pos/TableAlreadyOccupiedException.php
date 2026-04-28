<?php

namespace App\Exceptions\Pos;

use RuntimeException;

final class TableAlreadyOccupiedException extends RuntimeException
{
    public function __construct(
        public int $shopId,
        public int $tableId,
        ?string $message = null,
    ) {
        parent::__construct($message ?? __('pos.change_table_dest_occupied'));
    }
}
