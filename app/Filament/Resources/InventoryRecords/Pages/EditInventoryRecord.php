<?php

namespace App\Filament\Resources\InventoryRecords\Pages;

use App\Filament\Resources\InventoryRecords\InventoryRecordResource;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\EditRecord;

class EditInventoryRecord extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = InventoryRecordResource::class;
}
