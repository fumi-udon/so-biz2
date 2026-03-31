<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Services\TipCalculationService;
use App\Traits\RedirectsToIndex;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyTip extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = DailyTipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        app(TipCalculationService::class)->recalculateAmounts($this->record);
    }
}
