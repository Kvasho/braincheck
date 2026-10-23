<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Analytics\TestAnalytics;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TestAnalyticsRequest;
use App\Http\Resources\AnalyticsAttemptRowResource;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use App\Support\Listing;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Test analytics for the owner (teacher area) and admins (admin area, same
 * controller; TestPolicy::viewAnalytics). Real attempts only.
 */
final class TestAnalyticsController extends Controller
{
    public function show(TestAnalyticsRequest $request, Test $test): Response
    {
        $filters = $request->filters();
        $params = $request->listingParams();
        $area = $request->routeIs('admin.*') ? 'admin' : 'teacher';

        $listing = new Listing(
            searchable: [],
            sortable: ['submitted_at' => 'submitted_at', 'score_percent' => 'score_percent'],
            defaultSort: 'submitted_at',
        );

        $attempts = Attempt::query()->real()
            ->where('test_id', $test->id)
            ->when($filters['group'] !== null, fn ($query) => $query->whereIn('user_id', $filters['group']?->members()->select('users.id')))
            ->with('user:id,name,email')
            ->withCount('events');

        return Inertia::render('analytics/test', [
            'test' => [
                'ulid' => $test->ulid,
                'title' => $test->title,
                'results_visibility' => $test->results_visibility->value,
                'results_released_at' => $test->results_released_at?->toIso8601String(),
            ],
            'overview' => TestAnalytics::overview($test, $filters),
            'groups' => TestAnalytics::groups($test),
            'questions' => TestAnalytics::questions($test),
            'attemptRows' => Listing::toProps($listing->paginate($attempts, $params), AnalyticsAttemptRowResource::class, $request),
            'filters' => [
                ...$listing->state($params),
                'group' => $filters['group']?->ulid,
                'from' => $request->string('from')->toString() ?: null,
                'to' => $request->string('to')->toString() ?: null,
            ],
            'options' => [
                'groups' => $test->groups()->orderBy('name')->get(['groups.ulid', 'groups.name'])
                    ->map(fn (Group $group): array => ['value' => $group->ulid, 'label' => $group->name])->all(),
            ],
            'area' => $area,
            'canRelease' => $area === 'teacher' && $request->user()?->can('releaseResults', $test) === true,
        ]);
    }
}
