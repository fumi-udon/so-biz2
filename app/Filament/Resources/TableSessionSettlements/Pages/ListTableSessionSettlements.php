<?php

namespace App\Filament\Resources\TableSessionSettlements\Pages;

use App\Filament\Resources\TableSessionSettlements\TableSessionSettlementResource;
use Filament\Resources\Pages\ListRecords;

class ListTableSessionSettlements extends ListRecords
{
    protected static string $resource = TableSessionSettlementResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
