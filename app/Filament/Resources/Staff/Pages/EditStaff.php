<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\StaffResource;
use App\Support\FixedShiftsJson;
use App\Traits\RedirectsToIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStaff extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = StaffResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $raw = $data['fixed_shifts'] ?? null;
        $data['fixed_shifts'] = is_array($raw)
            ? FixedShiftsJson::mergeWithTemplate($raw)
            : FixedShiftsJson::mergeWithTemplate(null);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
