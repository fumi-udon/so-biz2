<?php

namespace App\Filament\Resources\DietaryBadges\Pages;

use App\Filament\Resources\DietaryBadges\DietaryBadgeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDietaryBadges extends ListRecords
{
    protected static string $resource = DietaryBadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
