<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Resources\UserDetailResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class UserController extends Controller
{
    private const int RECENT_ACTIVITY = 10;

    public function index(UserIndexRequest $request): Response
    {
        $listing = new Listing(
            searchable: ['name', 'email'],
            sortable: ['name' => 'name', 'created_at' => 'created_at', 'xp_total' => 'xp_total'],
            defaultSort: 'created_at',
        );

        $filters = $request->filters();

        $query = User::query()
            ->with('roles')
            ->when($filters['role'], fn (Builder $query, Role $role) => $query->role($role->value))
            ->when($filters['status'] === 'blocked', fn (Builder $query) => $query->whereNotNull('blocked_at'))
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->whereNull('blocked_at'))
            ->when($filters['verified'] === 'yes', fn (Builder $query) => $query->whereNotNull('email_verified_at'))
            ->when($filters['verified'] === 'no', fn (Builder $query) => $query->whereNull('email_verified_at'));

        $params = $request->listingParams();

        return Inertia::render('admin/users/index', [
            'users' => Listing::toProps($listing->paginate($query, $params), UserResource::class, $request),
            'filters' => [
                ...$listing->state($params),
                'role' => $filters['role']?->value,
                'status' => $filters['status'],
                'verified' => $filters['verified'],
            ],
            'roles' => Role::options(),
        ]);
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize('view', $user);

        $user->load('roles')->loadCount(['questions', 'tests', 'ownedGroups', 'attempts' => fn ($query) => $query->real()]);

        $activity = AuditLog::query()
            ->with(['actor', 'subject'])
            ->where(fn (Builder $query) => $query
                ->where('actor_id', $user->id)
                ->orWhere(fn (Builder $query) => $query
                    ->where('subject_type', $user->getMorphClass())
                    ->where('subject_id', $user->id)))
            ->latest('id')
            ->limit(self::RECENT_ACTIVITY)
            ->get();

        $admin = $request->user();

        return Inertia::render('admin/users/show', [
            'user' => (new UserDetailResource($user))->resolve($request),
            'activity' => AuditLogResource::collection($activity)->resolve($request),
            'roles' => Role::assignableOptions(),
            'can' => [
                'changeRole' => $admin?->can('changeRole', $user) === true,
                'block' => $admin?->can('block', $user) === true,
                'unblock' => $admin?->can('unblock', $user) === true,
            ],
        ]);
    }
}
