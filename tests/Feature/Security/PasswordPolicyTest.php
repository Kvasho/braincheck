<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

const STRONG_PASSWORD = 'Str0ng-Passw0rd!';

dataset('weak passwords', [
    'too short' => 'Sh0rt-Pw!',
    'no uppercase' => 'str0ng-passw0rd!',
    'no lowercase' => 'STR0NG-PASSW0RD!',
    'no number' => 'Strong-Password!',
    'no symbol' => 'Str0ngPassw0rd12',
    'common' => 'password',
]);

it('rejects weak passwords on registration', function (string $password): void {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
})->with('weak passwords');

it('accepts a strong password on registration', function (): void {
    $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

it('rejects weak passwords on reset', function (string $password): void {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with('weak passwords');

it('accepts a strong password on reset', function (): void {
    $user = User::factory()->create();

    $this->post(route('password.update'), [
        'token' => Password::createToken($user),
        'email' => $user->email,
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertSessionHasNoErrors();

    expect(Hash::check(STRONG_PASSWORD, $user->refresh()->password))->toBeTrue();
});

it('rejects weak passwords on change', function (string $password): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();
})->with('weak passwords');

it('accepts a strong password on change', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertSessionHasNoErrors();

    expect(Hash::check(STRONG_PASSWORD, $user->refresh()->password))->toBeTrue();
});
