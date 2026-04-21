<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\Forms\MenuItemForm;
use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    protected static string $resource = MenuItemResource::class;

    /**
     * CreateRecord は mutateFormDataBeforeFill を使わないため、既定フィールドを fill したうえで
     * options_payload の入れ子を正規化する。
     */
    protected function fillForm(): void
    {
        $this->callHook('beforeFill');

        $this->form->fill();

        $this->data = MenuItemForm::hydrateOptionsPayloadForForm($this->data ?? []);

        $this->callHook('afterFill');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
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
