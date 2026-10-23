<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Exceptions\RoleChangeNotAllowed;
use App\Models\User;

/**
 * Gives a brand-new user their first role (registration, make-admin, demo seeding).
 * It refuses users that already have a role: changes go through ChangeUserRole.
 */
final class AssignInitialRole
{
    /**
     * @throws RoleChangeNotAllowed
     */
    public function handle(User $user, Role $role): void
    {
        if ($user->roles()->exists()) {
            throw RoleChangeNotAllowed::alreadyHasRole();
        }

        $user->syncRoles([$role->value]);
        $user->unsetRelation('roles');
    }
}
