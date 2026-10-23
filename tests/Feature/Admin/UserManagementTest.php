<?php

declare(strict_types=1);

use App\Actions\Users\BlockUser;
use App\Actions\Users\ChangeUserRole;
use App\Enums\AuditAction;
use App\Enums\Role;
use App\Exceptions\RoleChangeNotAllowed;
use App\Exceptions\UserBlockNotAllowed;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function adminWith2fa(): User
{
    return User::factory()->admin()->withTwoFactor()->create();
}

/**
 * Acting as an admin whose password was just confirmed (password.confirm passes).
 */
function confirmedAdmin(): User
{
    $admin = adminWith2fa();
    test()->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    return $admin;
}

/**
 * Forget the in-memory login of the previous test request, so only cookies count.
 */
function forgetLogin(): void
{
    Auth::forgetGuards();
    app('session.store')->flush();
}

function insertSession(User $user, string $id): void
{
    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('x'),
        'last_activity' => time(),
    ]);
}

describe('listing', function (): void {
    it('lists users with exactly the allowed keys and no secrets', function (): void {
        $admin = adminWith2fa();
        User::factory()->student()->withTwoFactor()->create(['remember_token' => 'remember-me-token-value']);

        $response = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();

        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 2)
            ->has('users.data.0', fn (Assert $row) => $row->hasAll([
                'ulid', 'name', 'email', 'role', 'verified', 'blocked', 'has_two_factor', 'xp_total', 'created_at',
            ]))
            ->where('users.meta.total', 2));

        $row = $response->viewData('page')['props']['users']['data'][0];
        expect(array_keys($row))->toBe(['ulid', 'name', 'email', 'role', 'verified', 'blocked', 'has_two_factor', 'xp_total', 'created_at']);

        expect((string) json_encode($response->viewData('page')['props']['users']))
            ->not->toContain('remember-me-token-value')
            ->not->toContain('two_factor_secret')
            ->not->toContain('recovery')
            ->not->toContain('$2y$');
    });

    it('searches by name and email, escaping wildcards', function (): void {
        $admin = adminWith2fa();
        User::factory()->student()->create(['name' => 'Nino Beridze', 'email' => 'nino@example.com']);
        User::factory()->student()->create(['name' => 'Giorgi', 'email' => 'g_iorgi@example.com']);
        User::factory()->student()->create(['name' => 'Luka', 'email' => 'luka@example.com']);

        $names = fn (string $search): array => collect(
            $this->actingAs($admin)->get(route('admin.users.index', ['search' => $search]))->viewData('page')['props']['users']['data'],
        )->pluck('name')->sort()->values()->all();

        expect($names('nino'))->toBe(['Nino Beridze'])
            ->and($names('g_i'))->toBe(['Giorgi'])
            ->and($names('%'))->toBe([]);
    });

    it('filters by role, status and verification', function (): void {
        $admin = adminWith2fa();
        User::factory()->teacher()->create(['name' => 'Teacher T']);
        User::factory()->student()->blocked()->create(['name' => 'Blocked B']);
        User::factory()->student()->unverified()->create(['name' => 'Unverified U']);

        $names = fn (array $query): array => collect(
            $this->actingAs($admin)->get(route('admin.users.index', $query))->viewData('page')['props']['users']['data'],
        )->pluck('name')->sort()->values()->all();

        expect($names(['role' => 'teacher']))->toBe(['Teacher T'])
            ->and($names(['status' => 'blocked']))->toBe(['Blocked B'])
            ->and($names(['verified' => 'no']))->toBe(['Unverified U']);
    });

    it('rejects an invalid filter value', function (): void {
        $this->actingAs(adminWith2fa())
            ->get(route('admin.users.index', ['role' => 'superuser']))
            ->assertSessionHasErrors('role');
    });

    it('sorts by whitelisted columns and ignores injection attempts', function (): void {
        $admin = adminWith2fa();
        User::factory()->student()->create(['name' => 'Ana', 'xp_total' => 10]);
        User::factory()->student()->create(['name' => 'Zura', 'xp_total' => 99]);

        $page = fn (array $query): array => $this->actingAs($admin)
            ->get(route('admin.users.index', $query))->assertOk()->viewData('page')['props'];

        // Only the two students matter (the admin's factory name is random).
        $order = fn (array $query): array => collect($page($query)['users']['data'])->pluck('name')->intersect(['Ana', 'Zura'])->values()->all();

        expect($order(['sort' => 'xp_total', 'direction' => 'desc']))->toBe(['Zura', 'Ana'])
            ->and($order(['sort' => 'name', 'direction' => 'asc']))->toBe(['Ana', 'Zura'])
            ->and($order(['sort' => 'name', 'direction' => 'desc']))->toBe(['Zura', 'Ana']);

        $injected = $page(['sort' => 'id; DROP TABLE users', 'direction' => 'asc; --']);
        expect($injected['filters']['sort'])->toBe('created_at')
            ->and($injected['filters']['direction'])->toBe('desc')
            ->and(User::query()->count())->toBe(3);
    });

    it('clamps the page size', function (): void {
        $props = $this->actingAs(adminWith2fa())
            ->get(route('admin.users.index', ['per_page' => 100000]))->assertOk()->viewData('page')['props'];

        expect($props['users']['meta']['per_page'])->toBe(100)
            ->and($props['filters']['per_page'])->toBe(100);
    });
});

describe('profile page', function (): void {
    it('shows the summary, counts and latest events with exact keys', function (): void {
        $admin = adminWith2fa();
        $student = User::factory()->student()->create();
        AuditLog::factory()->about($student)->create(['action' => AuditAction::UserBlocked->value]);

        $props = $this->actingAs($admin)->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/show'))
            ->viewData('page')['props'];

        expect(array_keys($props['user']))->toBe([
            'ulid', 'name', 'email', 'role', 'verified', 'blocked', 'has_two_factor', 'xp_total', 'created_at',
            'email_verified_at', 'blocked_at', 'blocked_reason', 'counts',
        ])
            ->and($props['user']['counts'])->toBe(['questions' => 0, 'tests' => 0, 'groups' => 0, 'attempts' => 0])
            ->and($props['activity'])->toHaveCount(1)
            ->and($props['activity'][0]['action'])->toBe('user.blocked')
            ->and($props['can'])->toBe(['changeRole' => true, 'block' => true, 'unblock' => true]);
    });

    it('is addressed by ULID only', function (): void {
        $student = User::factory()->student()->create();

        $this->actingAs(adminWith2fa())->get('/admin/users/'.$student->id)->assertNotFound();
    });
});

describe('changing a role', function (): void {
    it('requires password confirmation', function (): void {
        $student = User::factory()->student()->create();

        $this->actingAs(adminWith2fa())
            ->put(route('admin.users.role.update', $student), ['role' => 'teacher'])
            ->assertRedirect(route('password.confirm'));

        expect($student->fresh()?->role())->toBe(Role::Student)
            ->and(AuditLog::query()->count())->toBe(0);
    });

    it('changes the role and writes an audit entry', function (): void {
        $admin = confirmedAdmin();
        $student = User::factory()->student()->create();

        $this->put(route('admin.users.role.update', $student), ['role' => 'teacher'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($student->fresh()?->role())->toBe(Role::Teacher);

        $log = AuditLog::query()->where('action', AuditAction::UserRoleChanged->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->subject_id)->toBe($student->id)
            ->and($log->properties)->toBe(['from' => 'student', 'to' => 'teacher']);
    });

    it('validates the role', function (): void {
        confirmedAdmin();
        $student = User::factory()->student()->create();

        $this->put(route('admin.users.role.update', $student), ['role' => 'root'])->assertSessionHasErrors('role');
    });

    it('rejects self-demotion through the HTTP layer', function (): void {
        $admin = confirmedAdmin();
        adminWith2fa(); // another admin exists, so only the self rule applies

        $this->put(route('admin.users.role.update', $admin), ['role' => 'student'])
            ->assertRedirect()
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');

        expect($admin->fresh()?->role())->toBe(Role::Admin)
            ->and(AuditLog::query()->where('action', AuditAction::UserRoleChanged->value)->count())->toBe(0);
    });

    it('never leaves the platform without an active admin', function (): void {
        $lastActive = adminWith2fa();
        $blockedAdmin = User::factory()->admin()->blocked()->create();

        expect(fn () => app(ChangeUserRole::class)->handle($lastActive, Role::Teacher, $blockedAdmin))
            ->toThrow(RoleChangeNotAllowed::class);

        expect($lastActive->fresh()?->role())->toBe(Role::Admin);
    });
});

describe('blocking', function (): void {
    it('requires password confirmation', function (): void {
        $student = User::factory()->student()->create();

        $this->actingAs(adminWith2fa())
            ->post(route('admin.users.block', $student), ['reason' => 'Spam'])
            ->assertRedirect(route('password.confirm'));

        expect($student->fresh()?->isBlocked())->toBeFalse();
    });

    it('requires a reason', function (): void {
        confirmedAdmin();
        $student = User::factory()->student()->create();

        $this->post(route('admin.users.block', $student), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->post(route('admin.users.block', $student), ['reason' => str_repeat('x', 256)])->assertSessionHasErrors('reason');

        expect($student->fresh()?->isBlocked())->toBeFalse();
    });

    it('blocks, deletes all sessions, rotates the remember token and audits', function (): void {
        $admin = confirmedAdmin();
        $student = User::factory()->student()->create(['remember_token' => 'old-remember-token']);
        $bystander = User::factory()->student()->create();
        insertSession($student, 'student-session-1');
        insertSession($student, 'student-session-2');
        insertSession($bystander, 'bystander-session');

        $this->post(route('admin.users.block', $student), ['reason' => 'Cheating in exams'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $student->refresh();

        expect($student->isBlocked())->toBeTrue()
            ->and($student->blocked_reason)->toBe('Cheating in exams')
            ->and($student->getRememberToken())->not->toBe('old-remember-token')
            ->and(DB::table('sessions')->where('user_id', $student->id)->count())->toBe(0)
            ->and(DB::table('sessions')->where('user_id', $bystander->id)->count())->toBe(1);

        $log = AuditLog::query()->where('action', AuditAction::UserBlocked->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->subject_id)->toBe($student->id)
            ->and($log->properties)->toBe(['reason' => 'Cheating in exams']);
    });

    it('makes an existing "remember me" cookie useless, even after unblocking', function (): void {
        $student = User::factory()->student()->create();
        $student->setRememberToken('original-token');
        $student->save();

        $guard = Auth::guard('web');
        $cookie = $guard->getRecallerName();
        $recaller = $student->id.'|original-token|'.$student->getAuthPassword();

        // Control: the cookie works before the block.
        $this->withCookie($cookie, $recaller)->get(route('dashboard'))->assertOk();
        forgetLogin();

        app(BlockUser::class)->handle($student, 'Compromised account', adminWith2fa());
        app(App\Actions\Users\UnblockUser::class)->handle($student, adminWith2fa());
        forgetLogin();

        $this->withCookie($cookie, $recaller)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    });

    it('rejects blocking yourself', function (): void {
        $admin = confirmedAdmin();
        adminWith2fa();

        $this->post(route('admin.users.block', $admin), ['reason' => 'Testing'])
            ->assertSessionHas('inertia.flash_data.toast.type', 'error');

        expect($admin->fresh()?->isBlocked())->toBeFalse()
            ->and(AuditLog::query()->where('action', AuditAction::UserBlocked->value)->count())->toBe(0);
    });

    it('rejects blocking the last active admin', function (): void {
        $lastActive = adminWith2fa();
        $blockedAdmin = User::factory()->admin()->blocked()->create();

        expect(fn () => app(BlockUser::class)->handle($lastActive, 'Testing', $blockedAdmin))
            ->toThrow(UserBlockNotAllowed::class);

        expect($lastActive->fresh()?->isBlocked())->toBeFalse();
    });

    it('lets an admin block another admin while an active admin remains', function (): void {
        confirmedAdmin();
        $other = adminWith2fa();

        $this->post(route('admin.users.block', $other), ['reason' => 'Left the school'])->assertSessionHasNoErrors();

        expect($other->fresh()?->isBlocked())->toBeTrue();
    });

    it('unblocks and audits', function (): void {
        $admin = confirmedAdmin();
        $student = User::factory()->student()->blocked('Spam')->create();

        $this->delete(route('admin.users.unblock', $student))->assertRedirect()->assertSessionHasNoErrors();

        expect($student->fresh()?->isBlocked())->toBeFalse()
            ->and($student->fresh()?->blocked_reason)->toBeNull();

        $log = AuditLog::query()->where('action', AuditAction::UserUnblocked->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->properties)->toBe(['previous_reason' => 'Spam']);
    });
});
