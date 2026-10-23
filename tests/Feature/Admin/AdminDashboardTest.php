<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use App\Support\AdminDashboardStats;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function failedLogin(string $email, string $ip, ?DateTimeInterface $at = null): void
{
    AuditLog::factory()->create([
        'actor_id' => null,
        'action' => AuditAction::LoginFailed->value,
        'properties' => ['guard' => 'web', 'email' => $email],
        'ip_address' => $ip,
        'created_at' => $at ?? now(),
    ]);
}

it('computes the numbers from the data', function (): void {
    User::factory()->admin()->withTwoFactor()->create(['created_at' => now()->subDays(40)]);
    User::factory()->teacher()->create(['created_at' => now()->subDays(20)]); // teacher without 2FA
    User::factory()->teacher()->withTwoFactor()->create(['created_at' => now()->subDays(3)]);
    User::factory()->student()->count(2)->create(['created_at' => now()->subDay()]);
    User::factory()->student()->blocked()->create(['created_at' => now()->subDays(10)]);
    TeacherAccessRequest::factory()->count(2)->create(); // 2 more students, created now
    TeacherAccessRequest::factory()->rejected()->create(); // 1 more student + 1 admin reviewer

    failedLogin('a@example.com', '10.0.0.1');
    failedLogin('a@example.com', '10.0.0.1');
    failedLogin('b@example.com', '10.0.0.2');
    failedLogin('old@example.com', '10.0.0.9', now()->subDays(2));

    $stats = app(AdminDashboardStats::class)->get();

    expect($stats['users_by_role'])->toBe(['admin' => 2, 'teacher' => 2, 'student' => 6])
        ->and($stats['new_users_7d'])->toBe(7)
        ->and($stats['blocked_users'])->toBe(1)
        ->and($stats['pending_teacher_requests'])->toBe(2)
        ->and($stats['failed_logins_24h']['total'])->toBe(3)
        ->and($stats['failed_logins_24h']['top_ips'][0])->toBe(['ip' => '10.0.0.1', 'count' => 2])
        ->and($stats['failed_logins_24h']['top_emails'][0])->toBe(['email' => 'a@example.com', 'count' => 2])
        ->and(collect($stats['failed_logins_24h']['top_emails'])->pluck('email'))->not->toContain('old@example.com')
        // The factory reviewer admin and the teacher created without 2FA.
        ->and($stats['privileged_without_two_factor'])->toBe(2)
        ->and($stats['two_factor_enforced'])->toBeTrue()
        ->and($stats['registrations_30d'])->toHaveCount(30)
        ->and(collect($stats['registrations_30d'])->sum('count'))->toBe(9)
        ->and(collect($stats['registrations_30d'])->last())->toBe(['date' => now()->toDateString(), 'count' => 4]); // 3 requesters + 1 reviewer admin

});

it('caches the numbers for a minute', function (): void {
    User::factory()->student()->create();
    $service = app(AdminDashboardStats::class);

    expect($service->get()['users_by_role']['student'])->toBe(1);

    User::factory()->student()->create();
    expect($service->get()['users_by_role']['student'])->toBe(1);

    $this->travel(AdminDashboardStats::CACHE_SECONDS + 1)->seconds();
    expect($service->get()['users_by_role']['student'])->toBe(2);
});

it('runs a fixed number of queries, however much data there is', function (): void {
    $count = function (): int {
        app(AdminDashboardStats::class)->forget();
        DB::flushQueryLog();
        DB::enableQueryLog();
        app(AdminDashboardStats::class)->get();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };

    User::factory()->student()->create();
    $few = $count();

    User::factory()->student()->count(15)->create();
    TeacherAccessRequest::factory()->count(5)->create();
    $many = $count();

    expect($many)->toBe($few);
});

it('shows the stats and the last 10 events to admins only', function (): void {
    $admin = User::factory()->admin()->withTwoFactor()->create();
    AuditLog::factory()->count(12)->create();

    $this->actingAs($admin)->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard/admin')
            ->has('stats.users_by_role')
            ->has('activity', 10));

    foreach (['teacher', 'student'] as $role) {
        $this->actingAs(User::factory()->{$role}()->withTwoFactor()->create())->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component("dashboard/{$role}")
                ->missing('stats')
                ->missing('activity'));
    }
});
