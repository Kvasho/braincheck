<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Groups\CreateGroup;
use App\Actions\Groups\UpdateGroup;
use App\Enums\InviteExpiry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\GroupIndexRequest;
use App\Http\Requests\Teacher\GroupMemberIndexRequest;
use App\Http\Requests\Teacher\GroupRequest;
use App\Http\Requests\Teacher\StoreGroupRequest;
use App\Http\Requests\Teacher\UpdateGroupRequest;
use App\Http\Resources\GroupDetailResource;
use App\Http\Resources\GroupMemberResource;
use App\Http\Resources\GroupResource;
use App\Http\Resources\GroupTestResource;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use App\Models\User;
use App\Support\AppTime;
use App\Support\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's groups: list, create / edit and the group page (overview,
 * invite, members, tests). Owner only (GroupPolicy).
 */
final class GroupController extends Controller
{
    public const int ACTIVITY_LIMIT = 5;

    public function index(GroupIndexRequest $request): Response
    {
        $user = $request->user() ?? abort(403);
        $params = $request->listingParams();

        $listing = new Listing(
            searchable: ['name'],
            sortable: ['name' => 'name', 'created_at' => 'created_at'],
            defaultSort: 'created_at',
        );

        $query = Group::query()
            ->where('owner_id', $user->id)
            ->when($request->onlyArchived(), fn (Builder $query) => $query->onlyTrashed())
            ->withCount(['members', 'tests']);

        return Inertia::render('teacher/groups/index', [
            'groups' => Listing::toProps($listing->paginate($query, $params), GroupResource::class, $request),
            'filters' => [...$listing->state($params), 'archived' => $request->onlyArchived() ? 'only' : null],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Group::class);

        return Inertia::render('teacher/groups/form', [
            'group' => null,
            'options' => self::formOptions([InviteExpiry::Never, InviteExpiry::OneDay, InviteExpiry::SevenDays, InviteExpiry::ThirtyDays, InviteExpiry::Custom]),
        ]);
    }

    public function store(StoreGroupRequest $request, CreateGroup $createGroup): RedirectResponse
    {
        $group = $createGroup->handle($request->user() ?? abort(403), $request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group created. Share the invite code with your students.')]);

        return to_route('teacher.groups.show', ['group' => $group, 'tab' => 'invite']);
    }

    public function show(GroupMemberIndexRequest $request, Group $group): Response
    {
        $group->loadCount(['members', 'tests']);
        $testIds = $group->tests()->pluck('tests.id')->map(fn (mixed $id): int => (int) $id)->values()->all();
        $params = $request->listingParams();

        $listing = new Listing(
            searchable: ['users.name', 'users.email'],
            sortable: ['name' => 'users.name', 'joined_at' => 'group_user.joined_at'],
            defaultSort: 'joined_at',
        );

        $members = User::query()
            ->select(['users.*', 'group_user.joined_at as joined_at'])
            ->join('group_user', 'group_user.user_id', '=', 'users.id')
            ->where('group_user.group_id', $group->id)
            ->withCount(['attempts as group_attempts_count' => fn ($query) => $query->real()->whereIn('test_id', $testIds)]);

        return Inertia::render('teacher/groups/show', [
            'group' => (new GroupDetailResource($group))->resolve($request),
            'tab' => $request->tab(),
            'members' => Listing::toProps($listing->paginate($members, $params), GroupMemberResource::class, $request),
            'filters' => [...$listing->state($params), 'tab' => $request->tab()],
            'tests' => GroupTestResource::collection(
                $group->tests()->orderByPivot('assigned_at', 'desc')->get(),
            )->resolve($request),
            'assignableTests' => Test::query()
                ->where('owner_id', $group->owner_id)
                ->published()
                ->whereNotIn('id', $testIds)
                ->orderBy('title')
                ->get(['ulid', 'title'])
                ->map(fn (Test $test): array => ['value' => $test->ulid, 'label' => $test->title])
                ->all(),
            'activity' => self::activity($group, $testIds),
        ]);
    }

    public function edit(Group $group): Response
    {
        Gate::authorize('update', $group);

        $group->loadCount(['members', 'tests']);

        return Inertia::render('teacher/groups/form', [
            'group' => (new GroupDetailResource($group))->resolve(),
            'options' => self::formOptions(InviteExpiry::cases()),
        ]);
    }

    public function update(UpdateGroupRequest $request, Group $group, UpdateGroup $updateGroup): RedirectResponse
    {
        $updateGroup->handle($group, $request->settings());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Group saved.')]);

        return to_route('teacher.groups.show', $group);
    }

    /**
     * Recent joins and recently finished (real) attempts on the group's tests
     * by its current members.
     *
     * @param  array<int, int>  $testIds
     * @return array{joins: list<array{name: string, at: string|null}>, finished: list<array{name: string, test: string, at: string|null}>}
     */
    private static function activity(Group $group, array $testIds): array
    {
        $joins = $group->members()
            ->orderByPivot('joined_at', 'desc')
            ->limit(self::ACTIVITY_LIMIT)
            ->get(['users.id', 'users.name'])
            ->map(function (User $member): array {
                $joinedAt = $member->getRelation('pivot')->getAttribute('joined_at');

                return ['name' => $member->name, 'at' => is_string($joinedAt) ? now()->parse($joinedAt)->toIso8601String() : null];
            })
            ->all();

        $attempts = Attempt::query()->real()
            ->whereIn('test_id', $testIds)
            ->whereIn('user_id', $group->members()->select('users.id'))
            ->whereNotNull('submitted_at')
            ->with(['user:id,name', 'test:id,title'])
            ->latest('submitted_at')
            ->limit(self::ACTIVITY_LIMIT)
            ->get()
            ->map(fn (Attempt $attempt): array => [
                'name' => $attempt->user->name,
                'test' => $attempt->test->title,
                'at' => $attempt->submitted_at?->toIso8601String(),
            ])
            ->all();

        return ['joins' => array_values($joins), 'finished' => array_values($attempts)];
    }

    /**
     * @param  list<InviteExpiry>  $expiries
     * @return array<string, mixed>
     */
    private static function formOptions(array $expiries): array
    {
        return [
            'expiries' => array_map(fn (InviteExpiry $case): array => ['value' => $case->value, 'label' => $case->label()], $expiries),
            'timezone' => AppTime::zone(),
            'nameMax' => GroupRequest::NAME_MAX,
            'descriptionMax' => GroupRequest::DESCRIPTION_MAX,
        ];
    }
}
