<?php

namespace App\Data\Pos;

/**
 * V4: docs/technical_contract_v4.md §2.1
 */
final readonly class TableDashboardData
{
    /**
     * @param  list<TableTileAggregate>  $tiles
     */
    public function __construct(
        public array $tiles,
    ) {}
}
