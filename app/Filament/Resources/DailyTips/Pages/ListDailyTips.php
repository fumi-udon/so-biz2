<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTipAudits\DailyTipAuditResource;
use App\Filament\Resources\DailyTips\DailyTipResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;

class ListDailyTips extends ListRecords
{
    protected static string $resource = DailyTipResource::class;

    protected static ?string $title = 'Liste des pourboires';

    /**
     * En-tête personnalisé uniquement (pas d’appel à parent) : un seul bouton « Nouveau », pas de doublon.
     */
    protected function getHeaderActions(): array
    {
        $mario = 'transition-all !rounded-xl !font-black !text-white border-b-[6px] active:border-b-0 active:translate-y-[6px]';

        return [
            Action::make('dashboard')
                ->label('Tableau hebdo')
                ->icon('heroicon-o-calendar-days')
                ->url(DailyTipResource::getUrl('index'))
                ->extraAttributes([
                    'class' => $mario.' !border-sky-700 !bg-sky-500',
                ]),
            Action::make('calculate')
                ->label('Calcul des pourboires')
                ->icon('heroicon-o-calculator')
                ->url(DailyTipResource::getUrl('calculate'))
                ->extraAttributes([
                    'class' => $mario.' !border-emerald-700 !bg-emerald-500',
                ]),
            Action::make('logs')
                ->label('Journal d’audit')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(DailyTipAuditResource::getUrl('index'))
                ->extraAttributes([
                    'class' => $mario.' !border-amber-700 !bg-amber-400',
                ]),
            CreateAction::make()
                ->label('Nouveau')
                ->extraAttributes([
                    'class' => $mario.' !border-amber-800 !bg-amber-500',
                ]),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)
            ->contentGrid([
                'default' => 2,
                'md' => 3,
            ])
            ->recordClasses(fn (): string => 'rounded-2xl border-2 border-b-[6px] border-emerald-400/90 bg-emerald-50/50 p-2 shadow-sm ring-1 ring-emerald-200/70 dark:border-emerald-700 dark:bg-emerald-950/30 dark:ring-emerald-900/40');
    }
}
