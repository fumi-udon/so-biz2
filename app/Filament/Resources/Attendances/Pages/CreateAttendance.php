<?php

namespace App\Filament\Resources\Attendances\Pages;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Models\Attendance;
use App\Support\AttendanceFormSaveData;
use App\Traits\RedirectsToIndex;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Contracts\Support\Htmlable;

class CreateAttendance extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = AttendanceResource::class;

    public function getTitle(): string|Htmlable
    {
        return __('hq.action_new_punch', [], 'fr');
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('hq.form_create_submit', [], 'fr'));
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()
            ->label(__('hq.form_create_cancel', [], 'fr'));
    }

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
        $date = $data['date'] ?? null;

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
                ->title(__('hq.notify_duplicate_attendance', [], 'fr'))
                ->send();

            $this->redirect(AttendanceResource::getUrl('edit', ['record' => $existing]));
            throw new Halt;
        }
    }
}
