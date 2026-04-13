<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Models\DailyTip;
use App\Services\TipCalculationService;
use App\Traits\RedirectsToIndex;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDailyTip extends CreateRecord
{
    use RedirectsToIndex;

    protected static string $resource = DailyTipResource::class;

    protected static ?string $title = '💰 Nouveau pourboire';

    protected function getCreateFormAction(): Action
    {
        $mario = 'transition-all !rounded-xl !font-black !text-white border-b-[6px] active:border-b-0 active:translate-y-[6px]';

        return parent::getCreateFormAction()
            ->label('Enregistrer')
            ->extraAttributes([
                'class' => $mario.' !border-amber-700 !bg-amber-400',
            ]);
    }

    protected function getCancelFormAction(): Action
    {
        $mario = 'transition-all !rounded-xl !font-black !text-white border-b-[6px] active:border-b-0 active:translate-y-[6px]';

        return parent::getCancelFormAction()
            ->label('Annuler')
            ->extraAttributes([
                'class' => $mario.' !border-sky-700 !bg-sky-400',
            ]);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $record = DailyTip::updateOrCreate(
            [
                'business_date' => $data['business_date'],
                'shift' => $data['shift'],
            ],
            [
                'total_amount' => $data['total_amount'],
            ]
        );

        if (! $record->wasRecentlyCreated) {
            $record->distributions()->delete();
        }

        return $record;
    }

    protected function afterCreate(): void
    {
        app(TipCalculationService::class)->generateInitialDistributions($this->record);
    }
}
