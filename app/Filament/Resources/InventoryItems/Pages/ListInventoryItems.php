<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Exports\InventoryItemExporter;
use App\Filament\Imports\InventoryItemImporter;
use App\Filament\Resources\InventoryItems\InventoryItemResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(InventoryItemImporter::class),
            ExportAction::make()
                ->exporter(InventoryItemExporter::class),
            CreateAction::make(),
        ];
    }
}
