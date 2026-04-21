<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Exports\MenuItemExporter;
use App\Filament\Imports\MenuItemImporter;
use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(MenuItemImporter::class),
            ExportAction::make()
                ->exporter(MenuItemExporter::class),
            CreateAction::make(),
        ];
    }
}
