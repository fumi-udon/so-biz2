<?php

namespace App\Filament\Support;

use App\Models\User;
use Filament\Resources\Resource;

abstract class AdminOnlyResource extends Resource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user instanceof User && $user->hasFullFilamentAccess()) {
            return true;
        }
        if ($user?->isPiloteOnly()) {
            return static::piloteCanAccessThisResource();
        }

        return $user?->isAdmin() === true;
    }

    /**
     * `pilote` に許可するリソースだけ true を返す（既定は拒否）。
     */
    protected static function piloteCanAccessThisResource(): bool
    {
        return false;
    }
}
