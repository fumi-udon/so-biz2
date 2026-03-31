<?php

namespace App\Filament\Resources\CloseTask\Pages;

use App\Filament\Resources\CloseTaskResource;
use App\Traits\RedirectsToIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCloseTask extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = CloseTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
