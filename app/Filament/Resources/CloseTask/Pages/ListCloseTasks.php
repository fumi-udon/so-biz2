<?php

namespace App\Filament\Resources\CloseTask\Pages;

use App\Filament\Resources\CloseTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCloseTasks extends ListRecords
{
    protected static string $resource = CloseTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
