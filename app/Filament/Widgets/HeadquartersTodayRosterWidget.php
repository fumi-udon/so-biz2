<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Attendances\Widgets\TodayAttendanceRosterWidget;

/**
 * Tableau de bord siège : présences du jour (rafraîchissement 30 s, vue compacte).
 */
class HeadquartersTodayRosterWidget extends TodayAttendanceRosterWidget
{
    protected static string $view = 'filament.widgets.headquarters-today-roster';

    protected static ?int $sort = -9;

    public static function canView(): bool
    {
        $u = auth()->user();
        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        return $u?->hasRole($superAdmin) === true
            || $u?->hasRole('Owner') === true
            || $u?->isPiloteOnly() === true;
    }
}
