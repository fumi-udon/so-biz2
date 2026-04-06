<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use App\Support\AttendanceFormSaveData;
use App\Traits\RedirectsToIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendance extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = AttendanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var Attendance $record */
        $record = $this->record;
        $data = AttendanceFormSaveData::normalizeForRecord($record, $data);
        $data = AttendanceFormSaveData::finalizeForSave($data, $record);
        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);

        return $data;
    }
}
