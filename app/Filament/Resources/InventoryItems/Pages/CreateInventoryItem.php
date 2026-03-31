<?php

namespace App\Filament\Resources\InventoryItems\Pages;

use App\Filament\Resources\InventoryItems\InventoryItemResource;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryItem extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = InventoryItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('dropdown_options', $data)) {
            $data['options'] = $data['dropdown_options'];
            unset($data['dropdown_options']);
        }

        return $data;
    }
}
