<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Grading\AnonymousLabels;
use App\Domain\Grading\GradingQueue;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\GradingQueueRequest;
use App\Http\Resources\GradingQueueItemResource;
use App\Models\AttemptAnswer;
use App\Models\Group;
use App\Models\Test;
use App\Support\Listing;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The manual grading queue: answers needing a teacher in the teacher's own
 * tests, oldest submission first, with counts per test. Anonymous by
 * default ("Student #N"); identities are revealed one at a time (audited).
 */
final class GradingController extends Controller
{
    public function index(GradingQueueRequest $request): Response
    {
        $user = $request->user() ?? abort(403);
        $filters = $request->filters();
        $params = $request->listingParams();
        $anonymous = (bool) $request->session()->get(AnonymousLabels::SESSION_KEY, true);

        $test = $filters['test'] === null ? null : Test::query()->withTrashed()->where('owner_id', $user->id)->where('ulid', $filters['test'])->first();
        $group = $filters['group'] === null ? null : Group::query()->where('owner_id', $user->id)->where('ulid', $filters['group'])->first();

        $listing = new Listing(
            searchable: [],
            sortable: ['submitted_at' => GradingQueue::orderBySubmission(...)],
            defaultSort: 'submitted_at',
            defaultDirection: 'asc',
        );

        $page = $listing->paginate(
            GradingQueue::pending($user, $test, $group)->with(['attemptQuestion.attempt.test:id,ulid,title', 'attemptQuestion.attempt.user:id,name']),
            $params,
        );

        $items = collect($page->items());
        $labels = AnonymousLabels::forTests(array_values($items->map(fn (AttemptAnswer $answer): int => $answer->attemptQuestion->attempt->test_id)->unique()->all()));

        $items->each(function (AttemptAnswer $answer) use ($anonymous, $labels): void {
            $attempt = $answer->attemptQuestion->attempt;
            $answer->setAttribute('student_label', $anonymous
                ? __('Student #:number', ['number' => $labels[$attempt->test_id][$attempt->user_id] ?? 0])
                : $attempt->user->name);
        });

        return Inertia::render('teacher/grading/index', [
            'answers' => Listing::toProps($page, GradingQueueItemResource::class, $request),
            'counts' => GradingQueue::countsPerTest($user),
            'filters' => [...$listing->state($params), 'test' => $test?->ulid, 'group' => $group?->ulid],
            'options' => [
                'groups' => Group::query()->where('owner_id', $user->id)->orderBy('name')->get(['ulid', 'name'])
                    ->map(fn (Group $group): array => ['value' => $group->ulid, 'label' => $group->name])->all(),
            ],
            'anonymous' => $anonymous,
        ]);
    }
}
