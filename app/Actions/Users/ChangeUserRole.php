<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Events\UserRoleChanged;
use App\Exceptions\RoleChangeNotAllowed;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only way to change a user's role. The user always ends up with exactly one role.
 */
final class ChangeUserRole
{
    /**
     * @throws RoleChangeNotAllowed
     */
    public function handle(User $user, Role $role, User $actor): void
    {
        DB::transaction(function () use ($user, $role, $actor): void {
            // Lock every admin row so two concurrent demotions can't both pass the
            // "last admin" check and leave the platform without an administrator.
            // Blocked admins can't administer anything, so only active ones count.
            $activeAdminIds = User::query()
                ->role(Role::Admin->value)
                ->whereNull('blocked_at')
                ->lockForUpdate()
                ->pluck('id');

            $user->unsetRelation('roles');
            $oldRole = $user->roleOrNull();

            if ($oldRole === Role::Admin && $role !== Role::Admin) {
                if ($actor->is($user)) {
                    throw RoleChangeNotAllowed::cannotDemoteSelf();
                }

                if ($activeAdminIds->reject(fn (mixed $id): bool => (int) $id === $user->id)->isEmpty()) {
                    throw RoleChangeNotAllowed::lastAdmin();
                }
            }

            if ($oldRole === $role) {
                return;
            }

            $user->syncRoles([$role->value]);
            $user->unsetRelation('roles');

            UserRoleChanged::dispatch($user, $actor, $oldRole, $role);
        });
    }
}
