<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Group;
use App\Models\User;

/**
 * Group policy: deny by default.
 *
 * Denied by default; only the rules below are defined so far. Everything else is
 * added (and tested) by the stage that implements it.
 */
final class GroupPolicy
{
    /**
     * Determine whether the user can list any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GroupsViewAny->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Group $group): bool
    {
        return $user->can(Permission::GroupsViewAny->value)
            || ($group->owner_id === $user->id && $user->can(Permission::GroupsManageOwn->value));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::GroupsManageOwn->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Group $group): bool
    {
        return $this->managesOwn($user, $group);
    }

    /**
     * Archive (soft delete): owner only.
     */
    public function delete(User $user, Group $group): bool
    {
        return $this->managesOwn($user, $group);
    }

    /**
     * Reveal the secret invite code (and its QR code): owner only.
     */
    public function viewInviteCode(User $user, Group $group): bool
    {
        return $this->managesOwn($user, $group);
    }

    /**
     * A student leaves a group they are a member of.
     */
    public function leave(User $user, Group $group): bool
    {
        return $user->can(Permission::GroupsJoin->value)
            && $group->exists
            && ! $group->trashed()
            && $group->members()->whereKey($user->id)->exists();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Group $group): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Group $group): bool
    {
        return false;
    }

    private function managesOwn(User $user, Group $group): bool
    {
        return $group->owner_id === $user->id
            && ! $group->trashed()
            && $user->can(Permission::GroupsManageOwn->value);
    }
}
