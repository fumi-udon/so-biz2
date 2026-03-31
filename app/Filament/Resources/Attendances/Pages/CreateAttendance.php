<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Support\AttendanceFormSaveData;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendance extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = AttendanceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return AttendanceFormSaveData::normalizeForCreate($data);
    }
}
