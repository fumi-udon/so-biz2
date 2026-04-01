<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard;

class HeadquartersDashboard extends Dashboard
{
    protected static ?string $title = '本部ダッシュボード';

    protected static ?string $navigationLabel = '本部ダッシュボード';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->isAdmin() === true || $user?->isCashier() === true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public function mount(): void
    {
        if (auth()->user()?->isCashier() === true) {
            $this->redirectRoute('filament.admin.pages.daily-close-check', navigate: true);
        }
    }
}
