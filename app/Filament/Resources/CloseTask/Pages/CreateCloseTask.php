<?php

namespace App\Filament\Resources\CloseTask\Pages;

use App\Filament\Resources\CloseTaskResource;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateCloseTask extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = CloseTaskResource::class;
}
