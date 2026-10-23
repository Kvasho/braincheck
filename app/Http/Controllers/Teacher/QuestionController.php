<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Questions\ArchiveQuestion;
use App\Actions\Questions\CreateQuestion;
use App\Actions\Questions\UpdateQuestion;
use App\Enums\TestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\QuestionIndexRequest;
use App\Http\Requests\Teacher\StoreQuestionRequest;
use App\Http\Requests\Teacher\UpdateQuestionRequest;
use App\Http\Resources\QuestionEditorResource;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Support\Listing;
use App\Support\QuestionListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The teacher's own question bank. Every query is scoped to the owner;
 * single-question actions are authorized by QuestionPolicy (owner only).
 */
final class QuestionController extends Controller
{
    public function index(QuestionIndexRequest $request, QuestionListing $listing): Response
    {
        $user = $request->user() ?? abort(403);

        $result = $listing->paginate($request->filters(), $request->listingParams(), Question::query()->ownedBy($user));

        return Inertia::render('teacher/questions/index', [
            'questions' => Listing::toProps($result['paginator'], QuestionResource::class, $request),
            'filters' => $result['filters'],
            'options' => QuestionListing::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Question::class);

        return Inertia::render('teacher/questions/form', [
            'question' => null,
            'options' => QuestionListing::options(),
            'liveTests' => 0,
        ]);
    }

    public function store(StoreQuestionRequest $request, CreateQuestion $createQuestion): RedirectResponse
    {
        $question = $createQuestion->handle($request->user() ?? abort(403), $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question created.')]);

        return to_route('teacher.questions.edit', $question);
    }

    public function edit(Request $request, Question $question): Response
    {
        Gate::authorize('update', $question);

        $question->load(['category', 'tags']);

        return Inertia::render('teacher/questions/form', [
            'question' => (new QuestionEditorResource($question))->resolve($request),
            'options' => QuestionListing::options(),
            // Published tests that already have attempts: editing is fine (attempts
            // keep their snapshot), but the teacher should know.
            'liveTests' => $question->tests()
                ->where('status', TestStatus::Published->value)
                ->whereHas('realAttempts')
                ->count(),
        ]);
    }

    public function update(UpdateQuestionRequest $request, Question $question, UpdateQuestion $updateQuestion): RedirectResponse
    {
        $updateQuestion->handle($question, $request->toData());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question saved.')]);

        return to_route('teacher.questions.edit', $question);
    }

    /**
     * Archive (soft delete).
     */
    public function destroy(Request $request, Question $question, ArchiveQuestion $archiveQuestion): RedirectResponse
    {
        Gate::authorize('delete', $question);

        $archiveQuestion->handle($question);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Question archived.')]);

        return to_route('teacher.questions.index');
    }
}
