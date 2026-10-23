<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;

it('grants every permission to at least one role (no orphans)', function (): void {
    $granted = collect(Role::permissions())->flatten()->unique()->map(fn (Permission $p): string => $p->value);

    expect(array_diff(Permission::values(), $granted->all()))->toBe([]);
});

it('never lists a permission twice for the same role', function (Role $role): void {
    $names = $role->permissionNames();

    expect($names)->toBe(array_values(array_unique($names)));
})->with(Role::cases());

it('never lets admins take exams', function (): void {
    expect(Role::Admin->grantedPermissions())->not->toContain(Permission::ExamsTake);
});

/*
 * Snapshot of the full role → permission matrix. Any change to Role::permissions()
 * must update this expectation, so it shows up as a visible, deliberate diff in review.
 */
it('matches the reviewed role → permission matrix', function (): void {
    $matrix = array_map(
        fn (array $permissions): array => array_map(fn (Permission $p): string => $p->value, $permissions),
        Role::permissions(),
    );

    expect($matrix)->toBe([
        'admin' => [
            'users.view',
            'users.manage-roles',
            'users.block',
            'categories.manage',
            'tags.manage',
            'audit-log.view',
            'questions.view-any',
            'tests.view-any',
            'groups.view-any',
            'attempts.regrade-any',
            'analytics.view-any',
            'leaderboard.view',
        ],
        'teacher' => [
            'questions.view-own',
            'questions.manage-own',
            'tests.view-own',
            'tests.manage-own',
            'groups.manage-own',
            'attempts.grade-own-tests',
            'analytics.view-own-tests',
            'leaderboard.view',
        ],
        'student' => [
            'groups.join',
            'teacher-request.create',
            'exams.take',
            'results.view-own',
            'certificates.view-own',
            'leaderboard.view',
        ],
    ]);
});

it('has a translated label for every role', function (Role $role): void {
    app()->setLocale('ka');

    expect($role->label())->not->toBe($role->name)->not->toBe('');
})->with(Role::cases());
