<?php

declare(strict_types=1);

use App\Models\User;

it('shares exactly the allowed auth.user keys', function (): void {
    $user = User::factory()->student()->create([
        'two_factor_secret' => encrypt('super-secret'),
        'two_factor_recovery_codes' => encrypt('["recovery-code"]'),
        'remember_token' => 'remember-me-token',
    ]);

    $page = $this->actingAs($user)->get(route('dashboard'))->assertOk()->viewData('page');

    expect(array_keys($page['props']['auth']['user']))
        ->toBe(['id', 'name', 'email', 'email_verified_at', 'role']);

    $json = json_encode($page, JSON_THROW_ON_ERROR);

    expect($json)
        ->not->toContain($user->password)
        ->not->toContain((string) $user->two_factor_secret)
        ->not->toContain((string) $user->two_factor_recovery_codes)
        ->not->toContain('remember-me-token');
});

it('shares a null user for guests', function (): void {
    $page = $this->get(route('home'))->assertOk()->viewData('page');

    expect($page['props']['auth']['user'])->toBeNull();
});

it('shares the locale and session flash status', function (): void {
    $page = $this->withSession(['status' => 'verification-link-sent'])
        ->get(route('home'))
        ->assertOk()
        ->viewData('page');

    expect($page['props']['locale'])->toBe(app()->getLocale())
        ->and($page['props']['flash'])->toBe(['status' => 'verification-link-sent']);
});
