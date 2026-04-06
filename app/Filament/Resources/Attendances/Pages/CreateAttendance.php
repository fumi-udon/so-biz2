<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use App\Support\AttendanceFormSaveData;
use App\Traits\RedirectsToIndex;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

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
        $data = AttendanceFormSaveData::normalizeForCreate($data);
        $data = AttendanceFormSaveData::finalizeForSave($data, null);
        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);

        return $data;
    }

    /**
     * 同じ staff_id + date の Attendance がすでに存在する場合は
     * 編集画面へリダイレクトし、INSERT を行わない。
     */
    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        $staffId = $data['staff_id'] ?? null;
        $date    = $data['date']     ?? null;

        if (! $staffId || ! $date) {
            return;
        }

        $existing = Attendance::query()
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->first();

        if ($existing !== null) {
            Notification::make()
                ->warning()
                ->title('既に登録済みです。編集画面を開きます。')
                ->send();

            $this->redirect(AttendanceResource::getUrl('edit', ['record' => $existing]));
            throw new Halt;
        }
    }
}
