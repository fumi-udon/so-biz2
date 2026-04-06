<?php

namespace App\Filament\Support;

use Filament\Resources\Resource;

abstract class AdminOnlyResource extends Resource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
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

