<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Services\TipCalculationService;
use App\Traits\RedirectsToIndex;
use Filament\Resources\Pages\CreateRecord;

class CreateDailyTip extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = DailyTipResource::class;

    protected function afterCreate(): void
    {
        app(TipCalculationService::class)->generateInitialDistributions($this->record);
    }
}
