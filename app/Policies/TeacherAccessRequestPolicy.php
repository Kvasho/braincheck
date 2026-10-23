<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\TeacherAccessRequest;
use App\Models\User;

/**
 * TeacherAccessRequest policy: deny by default.
 *
 * Students submit (teacher-request.create); admins list and review with
 * users.manage-roles. Business rules (one pending request, cooldown, only
 * pending requests can be reviewed) live in the Actions.
 */
final class TeacherAccessRequestPolicy
{
    /**
     * Determine whether the user can list any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersManageRoles->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, TeacherAccessRequest $teacherAccessRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::TeacherRequestCreate->value);
    }

    /**
     * Approve or reject the request.
     */
    public function review(User $user, TeacherAccessRequest $teacherAccessRequest): bool
    {
        return $user->can(Permission::UsersManageRoles->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, TeacherAccessRequest $teacherAccessRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, TeacherAccessRequest $teacherAccessRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, TeacherAccessRequest $teacherAccessRequest): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, TeacherAccessRequest $teacherAccessRequest): bool
    {
        return false;
    }
}
