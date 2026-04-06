<?php

namespace App\Filament\Resources\Shield;

use BezhanSalleh\FilamentShield\Resources\RoleResource as ShieldRoleResource;

/**
 * Filament Shield の Role リソースをアプリ側で差し替え、`pilote` 限定アカウントからの閲覧を拒否する。
 */
class RoleResource extends ShieldRoleResource
{
    public static function canAccess(): bool
    {
        if (auth()->user()?->isPiloteOnly()) {
            return false;
        }

        return parent::canAccess();
    }
}
