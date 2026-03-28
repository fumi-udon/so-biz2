<?php

namespace App\Filament\Resources\InventoryRecords\Pages;

use App\Filament\Resources\InventoryRecords\InventoryRecordResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryRecords extends ListRecords
{
    protected static string $resource = InventoryRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
