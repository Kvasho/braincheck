<?php

declare(strict_types=1);

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Users\ChangeUserRole;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use PragmaRX\Google2FA\Google2FA;

const AUDIT_NEW_PASSWORD = 'N3w-Sup3r-Secret!';

function onlyAuditEntry(): AuditLog
{
    expect(AuditLog::query()->count())->toBe(1);

    return AuditLog::query()->with(['actor', 'subject'])->sole();
}

function expectAuditEntry(AuditLog $log, AuditAction $action, ?User $actor, ?User $subject): void
{
    expect($log->action)->toBe($action->value)
        ->and($log->actor?->getKey())->toBe($actor?->getKey())
        ->and($log->subject_type)->toBe($subject?->getMorphClass())
        ->and($log->subject_id)->toBe($subject?->getKey());
}

it('records a successful login', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::Login, $user, $user);
    expect($log->ip_address)->toBe('127.0.0.1')
        ->and($log->user_agent)->not->toBeNull();
});

it('records a failed login with the attempted email only, never the password', function (): void {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'Wrong-Passw0rd!'])
        ->assertSessionHasErrors('email');

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::LoginFailed, null, null);
    expect($log->properties)->toBe(['guard' => 'web', 'email' => $user->email])
        ->and($log->ip_address)->toBe('127.0.0.1');

    $raw = (string) DB::table('audit_logs')->value('properties');
    expect($raw)->not->toContain('Wrong-Passw0rd!')->not->toContain('password');
});

it('records a failed login for an unknown email', function (): void {
    $this->post(route('login.store'), ['email' => 'nobody@example.com', 'password' => 'Wrong-Passw0rd!']);

    expect(onlyAuditEntry()->properties)->toBe(['guard' => 'web', 'email' => 'nobody@example.com']);
});

it('records a logout', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logout'))->assertRedirect();

    expectAuditEntry(onlyAuditEntry(), AuditAction::Logout, $user, $user);
});

it('records a password change', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => AUDIT_NEW_PASSWORD,
            'password_confirmation' => AUDIT_NEW_PASSWORD,
        ])
        ->assertSessionHasNoErrors();

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::PasswordChanged, $user, $user);
    expect($log->properties)->toBe([]);
});

it('records a password reset', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->post(route('password.update'), [
        'token' => $token,
        'email' => $user->email,
        'password' => AUDIT_NEW_PASSWORD,
        'password_confirmation' => AUDIT_NEW_PASSWORD,
    ])->assertSessionHasNoErrors();

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::PasswordReset, $user, $user);
    expect((string) DB::table('audit_logs')->value('properties'))->not->toContain($token);
});

it('records two-factor authentication once it is confirmed', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);

    $this->post(route('two-factor.enable'))->assertSessionHasNoErrors();
    expect(AuditLog::query()->count())->toBe(0);

    $secret = decrypt((string) $user->fresh()?->two_factor_secret);
    $code = app(Google2FA::class)->getCurrentOtp($secret);

    $this->post(route('two-factor.confirm'), ['code' => $code])->assertSessionHasNoErrors();

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::TwoFactorEnabled, $user, $user);
    expect((string) DB::table('audit_logs')->value('properties'))->not->toContain($code);
});

it('records disabling two-factor authentication', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('two-factor.disable'))
        ->assertSessionHasNoErrors();

    expectAuditEntry(onlyAuditEntry(), AuditAction::TwoFactorDisabled, $user, $user);
});

it('records regenerated recovery codes without storing them', function (): void {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('two-factor.regenerate-recovery-codes'))
        ->assertSessionHasNoErrors();

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::RecoveryCodesRegenerated, $user, $user);
    expect($log->properties)->toBe([]);
});

it('records a role change with the acting admin and the old and new role', function (): void {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    app(ChangeUserRole::class)->handle($student, Role::Teacher, $admin);

    $log = onlyAuditEntry();
    expectAuditEntry($log, AuditAction::UserRoleChanged, $admin, $student);
    expect($log->properties)->toBe(['from' => 'student', 'to' => 'teacher']);
});

it('records nothing when the role does not change', function (): void {
    $admin = User::factory()->admin()->create();
    $student = User::factory()->student()->create();

    app(ChangeUserRole::class)->handle($student, Role::Student, $admin);

    expect(AuditLog::query()->count())->toBe(0);
});

it('stores no IP address or user agent outside an HTTP request', function (): void {
    $user = User::factory()->create();

    $log = app(RecordAuditEvent::class)->handle(AuditAction::UserBlocked, null, $user, ['reason' => 'spam']);

    expect($log->fresh())
        ->ip_address->toBeNull()
        ->user_agent->toBeNull()
        ->actor_id->toBeNull()
        ->properties->toBe(['reason' => 'spam']);
});

it('sanitizes properties before they are stored', function (): void {
    app(RecordAuditEvent::class)->handle(AuditAction::UserBlocked, properties: [
        'reason' => 'spam',
        'password' => 'hunter2',
        'nested' => ['api_token' => 'abc', 'keep' => 'me'],
    ]);

    expect(AuditLog::query()->sole()->properties)->toBe([
        'reason' => 'spam',
        'nested' => ['keep' => 'me'],
    ]);
});
