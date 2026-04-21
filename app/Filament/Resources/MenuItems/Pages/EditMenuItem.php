<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\Forms\MenuItemForm;
use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMenuItem extends EditRecord
{
    protected static string $resource = MenuItemResource::class;

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
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return MenuItemForm::hydrateOptionsPayloadForForm($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['hero_image_disk'] = $data['hero_image_disk'] ?? 'public';
        $data['options_payload'] = MenuItemForm::normalizeOptionsPayloadBeforeSave(
            isset($data['options_payload']) && is_array($data['options_payload'])
                ? $data['options_payload']
                : null
        );

        return $data;
    }
}
