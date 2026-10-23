<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * User policy: deny by default.
 *
 * Denied by default; only the rules below are defined so far. Everything else is
 * added (and tested) by the stage that implements it.
 */
final class UserPolicy
{
    /**
     * Determine whether the user can list any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    /**
     * Change the model's role. Self-demotion and the last-admin rule are
     * enforced by ChangeUserRole (with a specific message).
     */
    public function changeRole(User $user, User $model): bool
    {
        return $user->can(Permission::UsersManageRoles->value);
    }

    /**
     * Block the model. Self-blocking and the last-admin rule are enforced by BlockUser.
     */
    public function block(User $user, User $model): bool
    {
        return $user->can(Permission::UsersBlock->value);
    }

    public function unblock(User $user, User $model): bool
    {
        return $user->can(Permission::UsersBlock->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
