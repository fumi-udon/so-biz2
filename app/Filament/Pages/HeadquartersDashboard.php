<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\HeadquartersAttendanceArcadeWidget;
use App\Filament\Widgets\HeadquartersCloseHistoryWidget;
use App\Filament\Widgets\HeadquartersStaffPayrollWidget;
use App\Filament\Widgets\HeadquartersStatsWidget;
use App\Filament\Widgets\HeadquartersTodayRosterWidget;
use App\Filament\Widgets\LowStockAlertWidget;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\Widget;

class HeadquartersDashboard extends Dashboard
{
    protected static ?string $title = 'Tableau de bord siège';

    protected static ?string $navigationLabel = 'Tableau de bord siège';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        return $user?->hasRole($superAdmin) === true
            || $user?->hasRole('Owner') === true
            || $user?->isPiloteOnly() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return array<int, class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [
            // HeadquartersStatsWidget::class,
            HeadquartersTodayRosterWidget::class,
            HeadquartersCloseHistoryWidget::class,
            HeadquartersAttendanceArcadeWidget::class,
            HeadquartersStaffPayrollWidget::class,
            LowStockAlertWidget::class,
            AccountWidget::class,
        ];
    }

    /**
     * @return int | string | array<string, int | string | null>
     */
    public function getColumns(): int|string|array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }
}
