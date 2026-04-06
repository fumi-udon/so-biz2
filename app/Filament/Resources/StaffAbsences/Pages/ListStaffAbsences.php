<?php

namespace App\Filament\Resources\StaffAbsences\Pages;

use App\Filament\Resources\StaffAbsences\StaffAbsenceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStaffAbsences extends ListRecords
{
    protected static string $resource = StaffAbsenceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
