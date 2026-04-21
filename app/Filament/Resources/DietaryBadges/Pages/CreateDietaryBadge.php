<?php

namespace App\Filament\Resources\DietaryBadges\Pages;

use App\Filament\Resources\DietaryBadges\DietaryBadgeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDietaryBadge extends CreateRecord
{
    protected static string $resource = DietaryBadgeResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['icon_disk'] = $data['icon_disk'] ?? 'public';

        return $data;
    }
}
