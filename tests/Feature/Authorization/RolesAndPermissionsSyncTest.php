<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/**
 * @return array{permissions: list<string>, roles: array<string, list<string>>}
 */
function permissionState(): array
{
    $roles = RoleModel::query()->with('permissions')->orderBy('name')->get()
        ->mapWithKeys(fn (RoleModel $role): array => [
            (string) $role->name => $role->permissions->pluck('name')->map(fn (mixed $n): string => (string) $n)->sort()->values()->all(),
        ])->all();

    return [
        'permissions' => PermissionModel::query()->orderBy('name')->pluck('name')->map(fn (mixed $n): string => (string) $n)->all(),
        'roles' => $roles,
    ];
}

it('makes the database match the enums exactly', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $state = permissionState();

    expect($state['permissions'])->toEqualCanonicalizing(Permission::values());

    expect(array_keys($state['roles']))->toEqualCanonicalizing(array_map(fn (Role $r): string => $r->value, Role::cases()));

    foreach (Role::cases() as $role) {
        expect($state['roles'][$role->value])->toEqualCanonicalizing($role->permissionNames());
    }

    expect(PermissionModel::query()->where('guard_name', '!=', 'web')->exists())->toBeFalse();
});

it('is idempotent', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $first = permissionState();
    $counts = [DB::table('permissions')->count(), DB::table('roles')->count(), DB::table('role_has_permissions')->count()];

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(permissionState())->toBe($first)
        ->and([DB::table('permissions')->count(), DB::table('roles')->count(), DB::table('role_has_permissions')->count()])->toBe($counts);
});

it('deletes permissions that are no longer in the enum', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $legacy = PermissionModel::findOrCreate('legacy.removed-feature', 'web');
    RoleModel::findByName(Role::Teacher->value, 'web')->givePermissionTo($legacy);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(PermissionModel::query()->where('name', 'legacy.removed-feature')->exists())->toBeFalse()
        ->and(RoleModel::findByName(Role::Teacher->value, 'web')->permissions->pluck('name')->all())
        ->toEqualCanonicalizing(Role::Teacher->permissionNames());
});

it('strips permissions from roles that are no longer in the enum', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $stale = RoleModel::findOrCreate('moderator', 'web');
    $stale->givePermissionTo(Permission::UsersBlock->value);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect($stale->fresh()?->permissions)->toBeEmpty();
});

it('never creates users', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed();

    expect(DB::table('users')->count())->toBe(0);
});

it('runs the same sync from the deploy command', function (): void {
    $this->artisan('braincheck:sync-permissions')->assertSuccessful();

    expect(permissionState()['permissions'])->toEqualCanonicalizing(Permission::values());

    $this->artisan('braincheck:sync-permissions')
        ->expectsOutputToContain('in sync')
        ->assertSuccessful();
});
