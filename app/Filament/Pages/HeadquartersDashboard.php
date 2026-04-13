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
        if ($user?->isPiloteOnly()) {
            return true;
        }

        return $user?->isAdmin() === true || $user?->isCashier() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();
        if ($user?->isPiloteOnly()) {
            return true;
        }

        return $user?->isAdmin() === true;
    }

    public function mount(): void
    {
        if (auth()->user()?->isCashier() === true) {
            $this->redirect(route('daily-close'), navigate: true);
        }
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
