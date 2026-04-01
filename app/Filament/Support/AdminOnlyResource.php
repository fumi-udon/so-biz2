<?php

namespace App\Filament\Support;

use Filament\Resources\Resource;

abstract class AdminOnlyResource extends Resource
{
    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }
}

