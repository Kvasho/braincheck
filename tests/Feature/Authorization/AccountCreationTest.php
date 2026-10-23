<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\DemoUsersSeeder;

describe('registration', function (): void {
    it('makes every self-registered user a Student, whatever the request says', function (array $injected): void {
        $this->post(route('register.store'), [
            'name' => 'Mallory',
            'email' => 'mallory@example.com',
            'password' => 'Str0ng-Passw0rd!',
            'password_confirmation' => 'Str0ng-Passw0rd!',
            ...$injected,
        ])->assertSessionHasNoErrors();

        $user = User::query()->where('email', 'mallory@example.com')->firstOrFail();

        expect($user->getRoleNames()->all())->toBe(['student'])
            ->and($user->can('users.manage-roles'))->toBeFalse();
    })->with([
        'role=admin' => [['role' => 'admin']],
        'roles[]=admin' => [['roles' => ['admin']]],
        'role=teacher + permissions' => [['role' => 'teacher', 'permissions' => ['users.manage-roles']]],
    ]);
});

describe('braincheck:make-admin', function (): void {
    it('creates a verified admin', function (): void {
        $this->artisan('braincheck:make-admin')
            ->expectsQuestion('Name', 'Root Admin')
            ->expectsQuestion('Email', 'root@braincheck.test')
            ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
            ->expectsQuestion('Confirm password', 'Str0ng-Passw0rd!')
            ->assertSuccessful();

        $admin = User::query()->where('email', 'root@braincheck.test')->firstOrFail();

        expect($admin->role())->toBe(Role::Admin)
            ->and($admin->hasVerifiedEmail())->toBeTrue()
            ->and($admin->can('users.manage-roles'))->toBeTrue();
    });

    it('rejects a weak password', function (): void {
        $this->artisan('braincheck:make-admin')
            ->expectsQuestion('Name', 'Root Admin')
            ->expectsQuestion('Email', 'root@braincheck.test')
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertFailed();

        expect(User::query()->where('email', 'root@braincheck.test')->exists())->toBeFalse();
    });

    it('rejects a mismatched confirmation', function (): void {
        $this->artisan('braincheck:make-admin')
            ->expectsQuestion('Name', 'Root Admin')
            ->expectsQuestion('Email', 'root@braincheck.test')
            ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
            ->expectsQuestion('Confirm password', 'Different-Passw0rd!')
            ->assertFailed();

        expect(User::query()->where('email', 'root@braincheck.test')->exists())->toBeFalse();
    });

    it('rejects a duplicate email', function (): void {
        User::factory()->student()->create(['email' => 'taken@braincheck.test']);

        $this->artisan('braincheck:make-admin')
            ->expectsQuestion('Name', 'Root Admin')
            ->expectsQuestion('Email', 'taken@braincheck.test')
            ->expectsQuestion('Password', 'Str0ng-Passw0rd!')
            ->expectsQuestion('Confirm password', 'Str0ng-Passw0rd!')
            ->assertFailed();

        expect(User::query()->where('email', 'taken@braincheck.test')->firstOrFail()->role())->toBe(Role::Student);
    });

    it('has no web route that can create an admin', function (): void {
        // Outside the admin area (/admin/*, which manages existing accounts), no
        // state-changing route mentions admins at all (e.g. no /make-admin).
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Illuminate\Routing\Route $route): bool => str_contains(strtolower($route->uri()), 'admin')
                && ! str_starts_with($route->uri(), 'admin/')
                && array_intersect($route->methods(), ['POST', 'PUT', 'PATCH']) !== [])
            ->map(fn (Illuminate\Routing\Route $route): string => $route->uri());

        expect($routes->all())->toBe([]);
    });

    it('refuses to grant the admin role through the admin area', function (): void {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $student = User::factory()->student()->create();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('admin.users.role.update', $student), ['role' => 'admin'])
            ->assertSessionHasErrors('role');

        expect($student->fresh()?->role())->toBe(Role::Student)
            ->and(User::query()->role('admin')->count())->toBe(1);
    });
});

describe('DemoUsersSeeder', function (): void {
    it('creates one admin, two teachers and five students', function (): void {
        $this->seed(DemoUsersSeeder::class);

        expect(User::query()->role('admin')->count())->toBe(1)
            ->and(User::query()->role('teacher')->count())->toBe(2)
            ->and(User::query()->role('student')->count())->toBe(5);

        $this->post(route('login.store'), ['email' => 'admin@braincheck.test', 'password' => DemoUsersSeeder::PASSWORD]);
        $this->assertAuthenticated();
    });

    it('is idempotent', function (): void {
        $this->seed(DemoUsersSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        expect(User::query()->count())->toBe(8);
    });

    it('refuses to run in production', function (): void {
        app()->detectEnvironment(fn (): string => 'production');

        // Invoke the seeder directly: `db:seed` itself would first ask for confirmation in production.
        expect(fn () => app(DemoUsersSeeder::class)->setContainer(app())->__invoke())
            ->toThrow(RuntimeException::class, 'only runs in the local or testing environment');

        expect(User::query()->count())->toBe(0);
    });
});
