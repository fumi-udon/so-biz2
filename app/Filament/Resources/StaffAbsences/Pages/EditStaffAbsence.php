<?php

namespace App\Filament\Resources\StaffAbsences\Pages;

use App\Filament\Resources\StaffAbsences\StaffAbsenceResource;
use App\Models\StaffAbsence;
use App\Traits\RedirectsToIndex;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditStaffAbsence extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = StaffAbsenceResource::class;

    protected function beforeSave(): void
    {
        $data = $this->form->getState();
        $staffId = $data['staff_id'] ?? null;
        $date = $data['date'] ?? null;
        $meal = $data['meal_type'] ?? null;

        if (! $staffId || ! $date || ! $meal) {
            return;
        }

        $exists = StaffAbsence::query()
            ->where('staff_id', $staffId)
            ->whereDate('date', $date)
            ->where('meal_type', $meal)
            ->where('id', '!=', $this->record->id)
            ->exists();

        if ($exists) {
            Notification::make()
                ->danger()
                ->title('同じスタッフ・日付・区分の欠勤は既に登録されています。')
                ->send();

            throw new Halt;
        }
    }
}
