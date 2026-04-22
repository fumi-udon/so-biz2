<?php

namespace App\Filament\Resources\Shield;

use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\RoleResource as ShieldRoleResource;

/**
 * Filament Shield の Role リソースをアプリ側で差し替え、`pilote` 限定アカウントからの閲覧を拒否する。
 */
class RoleResource extends ShieldRoleResource
{
    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user instanceof User && $user->hasFullFilamentAccess()) {
            return parent::canAccess();
        }
        if ($user?->isPiloteOnly()) {
            return false;
        }

        return parent::canAccess();
    }
}
