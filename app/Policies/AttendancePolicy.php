<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Attendance;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendancePolicy
{
    use HandlesAuthorization;

    /**
     * pilote への安全なアクションを一元管理するゲートウェイ。
     *
     * - 許可リスト（CRUD）に含まれる ability → true（以降のチェックをスキップして許可）
     * - 含まれない破壊的 ability → false（以降のチェックをスキップして拒否）
     * - pilote でないユーザー → null（Spatie の通常フローへ委譲）
     *
     * @return bool|null
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
        return $user->can('view_any_attendances::attendance');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Attendance $attendance): bool
    {
        return $user->can('view_attendances::attendance');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('create_attendances::attendance');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Attendance $attendance): bool
    {
        return $user->can('update_attendances::attendance');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attendance $attendance): bool
    {
        return $user->can('delete_attendances::attendance');
    }

    /**
     * Determine whether the user can bulk delete.
     */
    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_attendances::attendance');
    }

    /**
     * Determine whether the user can permanently delete.
     */
    public function forceDelete(User $user, Attendance $attendance): bool
    {
        return $user->can('force_delete_attendances::attendance');
    }

    /**
     * Determine whether the user can permanently bulk delete.
     */
    public function forceDeleteAny(User $user): bool
    {
        return $user->can('force_delete_any_attendances::attendance');
    }

    /**
     * Determine whether the user can restore.
     */
    public function restore(User $user, Attendance $attendance): bool
    {
        return $user->can('restore_attendances::attendance');
    }

    /**
     * Determine whether the user can bulk restore.
     */
    public function restoreAny(User $user): bool
    {
        return $user->can('restore_any_attendances::attendance');
    }

    /**
     * Determine whether the user can replicate.
     */
    public function replicate(User $user, Attendance $attendance): bool
    {
        return $user->can('replicate_attendances::attendance');
    }

    /**
     * Determine whether the user can reorder.
     */
    public function reorder(User $user): bool
    {
        return $user->can('reorder_attendances::attendance');
    }
}
