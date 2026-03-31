<?php

namespace App\Filament\Resources\JobLevels\Pages;

use App\Filament\Resources\JobLevels\JobLevelResource;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateJobLevel extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = JobLevelResource::class;
}
