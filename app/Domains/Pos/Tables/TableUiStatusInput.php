<?php

namespace App\Domains\Pos\Tables;

use DateTimeInterface;

final readonly class TableUiStatusInput
{
    public function __construct(
        public bool $hasActiveSession,
        public bool $hasUnackedPlaced,
        public ?DateTimeInterface $lastAdditionPrintedAt,
        public bool $hasOrdersAfterLastAdditionPrintedAt,
    ) {}
}
