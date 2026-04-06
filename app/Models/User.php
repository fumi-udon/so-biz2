<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    /** システム予約ロール名。DB の role レコードと必ず一致させること。 */
    public const ROLE_PILOTE = 'pilote';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');

        return $this->hasRole($superAdminName) || $this->roles()->exists();
    }

    /**
     * Filament ホワイトリスト: ROLE_PILOTE のみで super_admin（Shield 設定名）を持たないユーザー（デモ／限定アカウント）。
     */
    public function isPiloteOnly(): bool
    {
        $superAdminName = config('filament-shield.super_admin.name', 'super_admin');

        return $this->hasRole(self::ROLE_PILOTE) && ! $this->hasRole($superAdminName);
    }

    public function isAdmin(): bool
    {
        // Backward compatibility: existing users without role are treated as admin.
        return $this->role === null || $this->role === '' || $this->role === 'admin';
    }

    public function isCashier(): bool
    {
        return $this->role === 'cashier';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept(['password', 'remember_token'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
