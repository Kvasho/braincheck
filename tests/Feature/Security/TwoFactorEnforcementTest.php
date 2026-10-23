<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\TwoFactorEnforcement;

/**
 * @return array<string, array{Closure(): User, string}>
 */
function protectedAreas(): array
{
    return [
        'admin area' => [fn (): User => User::factory()->admin()->create(), 'admin.users.index'],
        'teacher area' => [fn (): User => User::factory()->teacher()->create(), 'teacher.questions.index'],
    ];
}

function useEnvironment(string $environment): void
{
    app()->detectEnvironment(fn (): string => $environment);
}

it('redirects admins and teachers without 2FA to the security settings', function (Closure $makeUser, string $route): void {
    $this->actingAs($makeUser())
        ->get(route($route))
        ->assertRedirect(route('security.edit'))
        ->assertSessionHas('inertia.flash_data.toast', [
            'type' => 'warning',
            'message' => __('Enable two-factor authentication to access this area.'),
        ]);
})->with(protectedAreas());

it('redirects users whose 2FA is set up but not confirmed', function (Closure $makeUser, string $route): void {
    $user = $makeUser();
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => null])->save();

    $this->actingAs($user)->get(route($route))->assertRedirect(route('security.edit'));
})->with(protectedAreas());

it('answers JSON requests with 403 instead of a redirect', function (Closure $makeUser, string $route): void {
    $this->actingAs($makeUser())
        ->getJson(route($route))
        ->assertForbidden()
        ->assertJson(['message' => __('Enable two-factor authentication to access this area.')]);
})->with(protectedAreas());

it('lets admins and teachers with confirmed 2FA through', function (Closure $makeUser, string $route): void {
    $user = $makeUser();
    $user->forceFill(['two_factor_secret' => encrypt('secret'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($user)->get(route($route))->assertOk();
})->with(protectedAreas());

it('does not affect students', function (): void {
    $student = User::factory()->student()->create();

    $this->actingAs($student)->get(route('student.groups.index'))->assertOk();
    // Other areas stay a plain 403 (role check first), not a 2FA redirect.
    $this->actingAs($student)->get(route('admin.users.index'))->assertForbidden();
    $this->actingAs($student)->get(route('teacher.questions.index'))->assertForbidden();
});

it('keeps the settings and 2FA setup reachable, so there is no redirect loop', function (): void {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    $this->get(route('security.edit'))->assertOk();
    $this->get(route('profile.edit'))->assertOk();
    $this->get(route('dashboard'))->assertOk();
    $this->post(route('two-factor.enable'))->assertSessionHasNoErrors()->assertRedirect();
    $this->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

it('can be switched off in the local environment only', function (): void {
    config(['braincheck.enforce_two_factor' => false]);
    useEnvironment('local');

    expect(app(TwoFactorEnforcement::class)->isEnabled())->toBeFalse();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.users.index'))
        ->assertOk();
});

it('is enforced by default in local', function (): void {
    useEnvironment('local');

    expect(app(TwoFactorEnforcement::class)->isEnabled())->toBeTrue();
});

it('can not be switched off outside local', function (string $environment): void {
    config(['braincheck.enforce_two_factor' => false]);
    useEnvironment($environment);

    expect(app(TwoFactorEnforcement::class)->isEnabled())->toBeTrue();

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('teacher.questions.index'))
        ->assertRedirect(route('security.edit'));
})->with(['production', 'staging', 'testing']);
