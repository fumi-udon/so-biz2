<?php

namespace App\Policies;

use App\Models\DailyTip;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DailyTipPolicy
{
    use HandlesAuthorization;

    /**
     * pilote への安全なアクションを一元管理するゲートウェイ（AttendancePolicy と同様）。
     *
     * - 許可リストに含まれる ability → true
     * - 含まれない破壊的 ability → false
     * - pilote でないユーザー → null（Spatie の通常フローへ委譲）
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isPiloteOnly()) {
            return null;
        }

        $allowed = ['viewAny', 'view', 'create', 'update'];

        return in_array($ability, $allowed, true) ? true : false;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DailyTip $dailyTip): bool
    {
        return $user->can('view_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DailyTip $dailyTip): bool
    {
        return $user->can('update_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DailyTip $dailyTip): bool
    {
        return $user->can('delete_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, DailyTip $dailyTip): bool
    {
        return $user->can('force_delete_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, DailyTip $dailyTip): bool
    {
        return $user->can('restore_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, DailyTip $dailyTip): bool
    {
        return $user->can('replicate_daily::tips::daily::tip');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_daily::tips::daily::tip');
    }
}
