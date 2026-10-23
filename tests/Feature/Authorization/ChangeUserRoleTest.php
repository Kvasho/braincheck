<?php

declare(strict_types=1);

use App\Actions\Users\AssignInitialRole;
use App\Actions\Users\ChangeUserRole;
use App\Enums\Role;
use App\Events\UserRoleChanged;
use App\Exceptions\RoleChangeNotAllowed;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function changeRole(User $user, Role $role, User $actor): void
{
    app(ChangeUserRole::class)->handle($user, $role, $actor);
}

it('leaves the user with exactly one role', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    changeRole($user, Role::Teacher, $admin);

    expect($user->fresh()?->roles->pluck('name')->all())->toBe(['teacher'])
        ->and($user->fresh()?->role())->toBe(Role::Teacher);

    changeRole($user, Role::Admin, $admin);

    expect($user->fresh()?->roles->pluck('name')->all())->toBe(['admin']);
});

it('updates the permissions that come with the role', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    expect($user->can('exams.take'))->toBeTrue()
        ->and($user->can('questions.manage-own'))->toBeFalse();

    changeRole($user, Role::Teacher, $admin);
    $user = $user->fresh();

    expect($user?->can('exams.take'))->toBeFalse()
        ->and($user?->can('questions.manage-own'))->toBeTrue();
});

it('never demotes the last admin', function (): void {
    $onlyAdmin = User::factory()->admin()->create();
    $otherActor = User::factory()->admin()->create();

    changeRole($otherActor, Role::Teacher, $onlyAdmin);

    expect(fn () => changeRole($onlyAdmin, Role::Teacher, $otherActor))
        ->toThrow(RoleChangeNotAllowed::class);

    expect($onlyAdmin->fresh()?->role())->toBe(Role::Admin);
});

it('does not let an admin demote themselves', function (): void {
    $admin = User::factory()->admin()->create();
    User::factory()->admin()->create();

    expect(fn () => changeRole($admin, Role::Student, $admin))
        ->toThrow(RoleChangeNotAllowed::class);

    expect($admin->fresh()?->role())->toBe(Role::Admin);
});

it('lets another admin demote an admin while more than one remains', function (): void {
    $admin = User::factory()->admin()->create();
    $target = User::factory()->admin()->create();

    changeRole($target, Role::Teacher, $admin);

    expect($target->fresh()?->role())->toBe(Role::Teacher);
});

it('dispatches UserRoleChanged with actor, old and new role', function (): void {
    Event::fake([UserRoleChanged::class]);

    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    changeRole($user, Role::Teacher, $admin);

    Event::assertDispatched(UserRoleChanged::class, fn (UserRoleChanged $event): bool => $event->user->is($user)
        && $event->actor->is($admin)
        && $event->oldRole === Role::Student
        && $event->newRole === Role::Teacher);
});

it('does not dispatch an event when the role does not change', function (): void {
    Event::fake([UserRoleChanged::class]);

    $admin = User::factory()->admin()->create();
    $user = User::factory()->student()->create();

    changeRole($user, Role::Student, $admin);

    Event::assertNotDispatched(UserRoleChanged::class);
});

it('refuses to assign an initial role twice', function (): void {
    $user = User::factory()->student()->create();

    expect(fn () => app(AssignInitialRole::class)->handle($user, Role::Admin))
        ->toThrow(RoleChangeNotAllowed::class);

    expect($user->fresh()?->role())->toBe(Role::Student);
});
