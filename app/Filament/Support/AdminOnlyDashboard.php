<?php

namespace App\Filament\Support;

use Filament\Pages\Dashboard;

abstract class AdminOnlyDashboard extends Dashboard
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}

