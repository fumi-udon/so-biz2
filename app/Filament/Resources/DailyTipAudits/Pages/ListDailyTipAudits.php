<?php

namespace App\Filament\Resources\DailyTipAudits\Pages;

use App\Filament\Resources\DailyTipAudits\DailyTipAuditResource;
use Filament\Resources\Pages\ListRecords;

class ListDailyTipAudits extends ListRecords
{
    protected static string $resource = DailyTipAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
