<?php

namespace App\Filament\Resources\CloseTask\Pages;

use App\Filament\Resources\CloseTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCloseTask extends EditRecord
{
    protected static string $resource = CloseTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
