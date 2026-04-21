<?php

namespace App\Filament\Resources\DietaryBadges\Pages;

use App\Filament\Resources\DietaryBadges\DietaryBadgeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDietaryBadge extends EditRecord
{
    protected static string $resource = DietaryBadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['icon_disk'] = $data['icon_disk'] ?? 'public';

        return $data;
    }
}
