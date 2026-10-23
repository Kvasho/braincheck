<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\CreateTest;
use App\Actions\Tests\UpdateTest;
use App\Domain\Tests\PublishChecklist;
use App\Enums\QuestionSelectionMode;
use App\Enums\ResultsVisibility;
use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreTestRequest;
use App\Http\Requests\Teacher\TestIndexRequest;
use App\Http\Requests\Teacher\UpdateTestRequest;
use App\Http\Resources\TestBuilderResource;
use App\Http\Resources\TestResource;
use App\Models\Category;
use App\Models\Test;
use App\Support\AppTime;
use App\Support\CategoryTree;
use App\Support\Listing;
use App\Support\QuestionListing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's tests: list, create (step one) and the builder (explicit save).
 */
final class TestController extends Controller
{
    public function index(TestIndexRequest $request): Response
    {
        $user = $request->user() ?? abort(403);
        $filters = $request->filters();
        $params = $request->listingParams();

        $listing = new Listing(
            searchable: ['title'],
            sortable: ['updated_at' => 'updated_at', 'title' => 'title', 'opens_at' => 'opens_at'],
            defaultSort: 'updated_at',
        );

        $query = Test::query()
            ->where('owner_id', $user->id)
            ->with('category')
            ->withCount(['questions', 'groups', 'realAttempts'])
            ->when($filters['status'], fn (Builder $query, TestStatus $status) => $query->where('status', $status->value))
            ->when($filters['category'], fn (Builder $query, string $ulid) => $query->whereIn('category_id', Category::query()->where('ulid', $ulid)->pluck('id')));

        return Inertia::render('teacher/tests/index', [
            'tests' => Listing::toProps($listing->paginate($query, $params), TestResource::class, $request),
            'filters' => [...$listing->state($params), 'status' => $filters['status']?->value, 'category' => $filters['category']],
            'options' => self::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Test::class);

        return Inertia::render('teacher/tests/create', ['options' => self::options()]);
    }

    public function store(StoreTestRequest $request, CreateTest $createTest): RedirectResponse
    {
        $test = $createTest->handle($request->user() ?? abort(403), $request->title(), $request->description(), $request->category());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test created. Add questions next.')]);

        return to_route('teacher.tests.edit', $test);
    }

    public function edit(Request $request, Test $test, PublishChecklist $checklist): Response
    {
        Gate::authorize('update', $test);

        $test->load('category');

        return Inertia::render('teacher/tests/builder', [
            'test' => (new TestBuilderResource($test))->resolve($request),
            'checklist' => $checklist->check($test),
            'attemptsCount' => $test->realAttempts()->count(),
            'questionOptions' => QuestionListing::options(),
            'options' => self::options(),
        ]);
    }

    public function update(UpdateTestRequest $request, Test $test, UpdateTest $updateTest): RedirectResponse
    {
        $updateTest->handle($test, $request->settings(), $request->questionLinks());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Test saved.')]);

        return to_route('teacher.tests.edit', $test);
    }

    /**
     * @return array<string, mixed>
     */
    private static function options(): array
    {
        return [
            'categories' => app(CategoryTree::class)->get(),
            'statuses' => array_map(fn (TestStatus $case): array => ['value' => $case->value, 'label' => $case->label()], TestStatus::cases()),
            'visibilities' => array_map(fn (ResultsVisibility $case): array => ['value' => $case->value, 'label' => $case->label(), 'rank' => $case->revealRank()], ResultsVisibility::cases()),
            'selectionModes' => array_map(fn (QuestionSelectionMode $case): array => ['value' => $case->value, 'label' => $case->label()], QuestionSelectionMode::cases()),
            'timezone' => AppTime::zone(),
        ];
    }
}
