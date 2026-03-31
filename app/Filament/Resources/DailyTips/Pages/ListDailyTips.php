<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTipAudits\DailyTipAuditResource;
use App\Filament\Resources\DailyTips\DailyTipResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDailyTips extends ListRecords
{
    protected static string $resource = DailyTipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('dashboard')
                ->label('週間支払い表')
                ->icon('heroicon-o-calendar-days')
                ->url(DailyTipResource::getUrl('index')),
            Action::make('calculate')
                ->label('チップ計算')
                ->icon('heroicon-o-calculator')
                ->url(DailyTipResource::getUrl('calculate')),
            Action::make('logs')
                ->label('Log')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('warning')
                ->url(DailyTipAuditResource::getUrl('index')),
            CreateAction::make(),
        ];
    }
}
