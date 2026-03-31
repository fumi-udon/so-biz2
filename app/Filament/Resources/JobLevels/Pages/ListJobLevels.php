<?php

namespace App\Filament\Resources\JobLevels\Pages;

use App\Filament\Resources\JobLevels\JobLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobLevels extends ListRecords
{
    protected static string $resource = JobLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
