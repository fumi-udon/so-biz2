<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateStaff extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = StaffResource::class;
}
