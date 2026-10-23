<?php

declare(strict_types=1);

use App\Models\User;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

it('does not let a blocked user log in', function (): void {
    $user = User::factory()->student()->blocked()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('shows a blocked user exactly the wrong-password message', function (): void {
    $blocked = User::factory()->student()->blocked()->create();
    $active = User::factory()->student()->create();

    $blockedError = $this->post(route('login.store'), ['email' => $blocked->email, 'password' => 'password'])
        ->assertSessionHasErrors('email')
        ->getSession()?->get('errors')?->first('email');

    $this->flushSession();

    $wrongPasswordError = $this->post(route('login.store'), ['email' => $active->email, 'password' => 'not-the-password'])
        ->assertSessionHasErrors('email')
        ->getSession()?->get('errors')?->first('email');

    expect($blockedError)->toBe($wrongPasswordError)->not->toBeNull();
});

it('logs a user out on their next request after being blocked mid-session', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $user->forceFill(['blocked_at' => now(), 'blocked_reason' => 'Abuse'])->save();

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();

    // Neutral message: never reveals that (or why) the account was blocked.
    expect(session('status'))->toBeString()
        ->not->toContain('Abuse')
        ->not->toContain('block');
});

it('rotates the CSRF token and session when kicking a blocked user out', function (): void {
    $user = User::factory()->student()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $tokenBefore = session()->token();

    $user->forceFill(['blocked_at' => now()])->save();
    $this->get(route('dashboard'));

    expect(session()->token())->not->toBe($tokenBefore);
});

it('does not let a blocked user sign in with a passkey', function (): void {
    $passkeyFor = function (User $user): Passkey {
        $passkey = new Passkey;
        $passkey->setRelation('user', $user);

        return $passkey;
    };

    $blocked = User::factory()->student()->blocked()->create();
    $active = User::factory()->student()->create();

    expect(Passkeys::allowsLogin(request(), $passkeyFor($blocked)))->toBeFalse()
        ->and(Passkeys::allowsLogin(request(), $passkeyFor($active)))->toBeTrue();
});

it('still lets an active user log in', function (): void {
    $user = User::factory()->student()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($user);
});
