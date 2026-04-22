<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Pages\Page;

abstract class AdminOnlyPage extends Page
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user instanceof User && $user->hasFullFilamentAccess()) {
            return true;
        }
        if ($user?->isPiloteOnly()) {
            return static::piloteCanAccessThisPage();
        }

        return $user?->isAdmin() === true;
    }

    /**
     * `pilote` に許可するページだけ true を返す（既定は拒否）。
     */
    protected static function piloteCanAccessThisPage(): bool
    {
        return false;
    }
}
