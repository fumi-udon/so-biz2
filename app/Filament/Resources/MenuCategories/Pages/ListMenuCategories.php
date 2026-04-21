<?php

namespace App\Filament\Resources\MenuCategories\Pages;

use App\Filament\Exports\MenuCategoryExporter;
use App\Filament\Imports\MenuCategoryImporter;
use App\Filament\Resources\MenuCategories\MenuCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ListRecords;

class ListMenuCategories extends ListRecords
{
    protected static string $resource = MenuCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImportAction::make()
                ->importer(MenuCategoryImporter::class),
            ExportAction::make()
                ->exporter(MenuCategoryExporter::class),
            CreateAction::make(),
        ];
    }
}
