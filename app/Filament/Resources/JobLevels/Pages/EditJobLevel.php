<?php

namespace App\Filament\Resources\JobLevels\Pages;

use App\Filament\Resources\JobLevels\JobLevelResource;
use App\Traits\RedirectsToIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditJobLevel extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = JobLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
