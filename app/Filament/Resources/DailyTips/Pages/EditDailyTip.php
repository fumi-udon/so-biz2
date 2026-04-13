<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Services\TipCalculationService;
use App\Traits\RedirectsToIndex;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDailyTip extends EditRecord
{
    use RedirectsToIndex;

    protected static string $resource = DailyTipResource::class;

    protected static ?string $title = '📝 Modifier le pourboire';

    protected function getSaveFormAction(): Action
    {
        $mario = 'transition-all !rounded-xl !font-black !text-white border-b-[6px] active:border-b-0 active:translate-y-[6px]';

        return parent::getSaveFormAction()
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

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Supprimer'),
        ];
    }

    protected function afterSave(): void
    {
        app(TipCalculationService::class)->recalculateAmounts($this->record);
    }
}
