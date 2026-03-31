<?php

namespace App\Filament\Resources\InventoryRecords\Pages;

use App\Filament\Resources\InventoryRecords\InventoryRecordResource;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryRecord extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = InventoryRecordResource::class;
}
