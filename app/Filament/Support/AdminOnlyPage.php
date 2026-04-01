<?php

namespace App\Filament\Support;

use Filament\Pages\Page;

abstract class AdminOnlyPage extends Page
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}

