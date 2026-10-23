<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AuditAction;
use App\Enums\Role;
use App\Enums\TeacherRequestStatus;
use App\Models\AuditLog;
use App\Models\TeacherAccessRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * Every number on the admin dashboard, computed with a handful of aggregate
 * queries (no per-row loading) and cached for CACHE_SECONDS.
 *
 * @phpstan-type Stats array{
 *     users_by_role: array<string, int>,
 *     new_users_7d: int,
 *     blocked_users: int,
 *     pending_teacher_requests: int,
 *     failed_logins_24h: array{total: int, top_ips: list<array{ip: string, count: int}>, top_emails: list<array{email: string, count: int}>},
 *     privileged_without_two_factor: int,
 *     two_factor_enforced: bool,
 *     registrations_30d: list<array{date: string, count: int}>,
 *     generated_at: string
 * }
 */
final readonly class AdminDashboardStats
{
    public const string CACHE_KEY = 'admin.dashboard.stats';

    public const int CACHE_SECONDS = 60;

    private const int TOP = 5;

    public function __construct(
        private Cache $cache,
        private TwoFactorEnforcement $twoFactorEnforcement,
    ) {}

    /**
     * @return Stats
     */
    public function get(): array
    {
        /** @var Stats */
        return $this->cache->remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => $this->compute());
    }

    public function forget(): void
    {
        $this->cache->forget(self::CACHE_KEY);
    }

    /**
     * @return Stats
     */
    private function compute(): array
    {
        $now = CarbonImmutable::now();

        return [
            'users_by_role' => $this->usersByRole(),
            'new_users_7d' => User::query()->where('created_at', '>=', $now->subDays(7))->count(),
            'blocked_users' => User::query()->whereNotNull('blocked_at')->count(),
            'pending_teacher_requests' => TeacherAccessRequest::query()->where('status', TeacherRequestStatus::Pending->value)->count(),
            'failed_logins_24h' => $this->failedLogins($now->subDay()),
            'privileged_without_two_factor' => User::query()
                ->role([Role::Admin->value, Role::Teacher->value])
                ->whereNull('blocked_at')
                ->where(fn ($query) => $query->whereNull('two_factor_secret')->orWhereNull('two_factor_confirmed_at'))
                ->count(),
            'two_factor_enforced' => $this->twoFactorEnforcement->isEnabled(),
            'registrations_30d' => $this->registrations($now),
            'generated_at' => $now->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function usersByRole(): array
    {
        $counts = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', (new User)->getMorphClass())
            ->groupBy('roles.name')
            ->selectRaw('roles.name as role, count(*) as total')
            ->pluck('total', 'role');

        $byRole = [];

        foreach (Role::cases() as $role) {
            $byRole[$role->value] = (int) ($counts[$role->value] ?? 0);
        }

        return $byRole;
    }

    /**
     * @return array{total: int, top_ips: list<array{ip: string, count: int}>, top_emails: list<array{email: string, count: int}>}
     */
    private function failedLogins(CarbonImmutable $since): array
    {
        $failed = fn () => AuditLog::query()
            ->where('action', AuditAction::LoginFailed->value)
            ->where('created_at', '>=', $since);

        $topIps = array_values($failed()
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->selectRaw('ip_address as ip, count(*) as occurrences')
            ->orderByDesc('occurrences')
            ->limit(self::TOP)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['ip' => (string) $row->ip, 'count' => (int) $row->occurrences])
            ->all());

        $topEmails = array_values($failed()
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(properties, '$.email')) as email, count(*) as occurrences")
            ->groupBy('email')
            ->havingRaw('email IS NOT NULL')
            ->orderByDesc('occurrences')
            ->limit(self::TOP)
            ->toBase()
            ->get()
            ->map(fn (object $row): array => ['email' => (string) $row->email, 'count' => (int) $row->occurrences])
            ->all());

        return [
            'total' => $failed()->count(),
            'top_ips' => $topIps,
            'top_emails' => $topEmails,
        ];
    }

    /**
     * One entry per day for the last 30 days (today included), zero-filled.
     *
     * @return list<array{date: string, count: int}>
     */
    private function registrations(CarbonImmutable $now): array
    {
        $start = $now->subDays(29)->startOfDay();

        $counts = User::query()
            ->where('created_at', '>=', $start)
            ->toBase()
            ->selectRaw('DATE(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $days = [];

        for ($day = $start; $day->lessThanOrEqualTo($now); $day = $day->addDay()) {
            $key = $day->toDateString();
            $days[] = ['date' => $key, 'count' => (int) ($counts[$key] ?? 0)];
        }

        return $days;
    }
}
