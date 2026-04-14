<?php

namespace App\Policies;

use App\Models\DailyTipAudit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DailyTipAuditPolicy
{
    use HandlesAuthorization;

    /**
     * pilote は監査ログを閲覧のみ（作成・更新・削除は不可）。
     */
    public function before(User $user, string $ability): ?bool
    {
        if (! $user->isPiloteOnly()) {
            return null;
        }

        $allowed = ['viewAny', 'view'];

        return in_array($ability, $allowed, true) ? true : false;
    }

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('view_any_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, DailyTipAudit $dailyTipAudit): bool
    {
        return $user->can('view_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, DailyTipAudit $dailyTipAudit): bool
    {
        return $user->can('update_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, DailyTipAudit $dailyTipAudit): bool
    {
        return $user->can('delete_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, DailyTipAudit $dailyTipAudit): bool
    {
        return $user->can('force_delete_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, DailyTipAudit $dailyTipAudit): bool
    {
        return $user->can('restore_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, DailyTipAudit $dailyTipAudit): bool
    {
        return $user->can('replicate_daily::tip::audits::daily::tip::audit');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_daily::tip::audits::daily::tip::audit');
    }
}
