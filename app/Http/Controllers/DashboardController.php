<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Analytics\PlatformAnalytics;
use App\Domain\Analytics\TeacherDashboard;
use App\Domain\Results\StudentDashboard;
use App\Enums\Permission;
use App\Enums\Role;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AdminDashboardStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    private const int RECENT_ACTIVITY = 10;

    /**
     * Render the dashboard for the user's role. The role only selects the page;
     * the data each page receives is guarded by permissions, so nothing can
     * leak between roles.
     */
    public function __invoke(Request $request, AdminDashboardStats $stats): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $role = $user->roleOrNull();

        abort_if($role === null, 403);

        $props = match ($role) {
            Role::Admin => $this->adminProps($request, $user, $stats),
            Role::Teacher => $user->can(Permission::AnalyticsViewOwnTests->value) ? ['dashboard' => TeacherDashboard::for($user)] : [],
            Role::Student => $user->can(Permission::ExamsTake->value) ? ['dashboard' => app(StudentDashboard::class)->for($user)] : [],
        };

        return Inertia::render('dashboard/'.$role->value, $props);
    }

    /**
     * @return array<string, mixed>
     */
    private function adminProps(Request $request, User $user, AdminDashboardStats $stats): array
    {
        if (! $user->can('viewAny', User::class)) {
            return [];
        }

        return [
            'stats' => $stats->get(),
            'platform' => $user->can(Permission::AnalyticsViewAny->value) ? PlatformAnalytics::get() : null,
            'activity' => $user->can('viewAny', AuditLog::class)
                ? AuditLogResource::collection(
                    AuditLog::query()->with(['actor', 'subject'])->latest('id')->limit(self::RECENT_ACTIVITY)->get(),
                )->resolve($request)
                : [],
        ];
    }
}
