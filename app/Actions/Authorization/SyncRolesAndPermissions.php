<?php

declare(strict_types=1);

namespace App\Actions\Authorization;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

/**
 * Makes the database match App\Enums\Role and App\Enums\Permission exactly.
 *
 * Idempotent and safe in production: it never creates or touches users.
 * - creates missing permissions and roles,
 * - deletes permissions that are no longer in the enum,
 * - syncs every role's permissions to Role::permissions(),
 * - strips all permissions from roles that are no longer in the enum (kept, not
 *   deleted, so no user silently loses their role row; they just grant nothing),
 * - resets the permission cache.
 */
final class SyncRolesAndPermissions
{
    public function __construct(private readonly PermissionRegistrar $registrar) {}

    /**
     * @return array{created_permissions: list<string>, deleted_permissions: list<string>, created_roles: list<string>, stale_roles: list<string>}
     */
    public function handle(): array
    {
        $guard = $this->guard();

        $result = DB::transaction(function () use ($guard): array {
            $this->registrar->forgetCachedPermissions();

            $createdPermissions = [];

            foreach (Permission::cases() as $permission) {
                $model = PermissionModel::findOrCreate($permission->value, $guard);

                if ($model->wasRecentlyCreated) {
                    $createdPermissions[] = $permission->value;
                }
            }

            $stalePermissions = PermissionModel::query()
                ->where('guard_name', $guard)
                ->whereNotIn('name', Permission::values())
                ->get();

            $deletedPermissions = array_values($stalePermissions->pluck('name')->map(fn (mixed $name): string => (string) $name)->all());
            $stalePermissions->each->delete();

            $createdRoles = [];

            foreach (Role::cases() as $role) {
                $model = RoleModel::findOrCreate($role->value, $guard);

                if ($model->wasRecentlyCreated) {
                    $createdRoles[] = $role->value;
                }

                $model->syncPermissions($role->permissionNames());
            }

            $staleRoles = RoleModel::query()
                ->where('guard_name', $guard)
                ->whereNotIn('name', array_map(fn (Role $role): string => $role->value, Role::cases()))
                ->get();

            $staleRoles->each(fn (RoleModel $role): RoleModel => $role->syncPermissions([]));

            return [
                'created_permissions' => $createdPermissions,
                'deleted_permissions' => $deletedPermissions,
                'created_roles' => $createdRoles,
                'stale_roles' => array_values($staleRoles->pluck('name')->map(fn (mixed $name): string => (string) $name)->all()),
            ];
        });

        $this->registrar->forgetCachedPermissions();

        return $result;
    }

    private function guard(): string
    {
        $guard = config('auth.defaults.guard');

        return is_string($guard) ? $guard : 'web';
    }
}
