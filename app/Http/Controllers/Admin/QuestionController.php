<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionIndexRequest;
use App\Http\Resources\QuestionPreviewResource;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use App\Support\Listing;
use App\Support\QuestionListing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only browse of every teacher's questions, for moderation. Admins can
 * view and preview; there is no route to change anything.
 */
final class QuestionController extends Controller
{
    public function index(QuestionIndexRequest $request, QuestionListing $listing): Response
    {
        $result = $listing->paginate($request->filters(), $request->listingParams(), Question::query(), withOwner: true);

        return Inertia::render('admin/questions/index', [
            'questions' => Listing::toProps($result['paginator'], QuestionResource::class, $request),
            'filters' => $result['filters'],
            'options' => QuestionListing::options(),
        ]);
    }

    public function show(Request $request, Question $question, QuestionTypeRegistry $registry): Response
    {
        Gate::authorize('view', $question);

        $question->load(['owner', 'category', 'tags'])->loadCount('tests');

        return Inertia::render('admin/questions/show', [
            'question' => (new QuestionResource($question))->resolve($request),
            'preview' => (new QuestionPreviewResource($question))->resolve($request),
            'explanation' => $question->explanation?->toArray(),
            // Moderation: the correct answer, as the review shows it.
            'review' => $registry->for($question->type)->toReviewPayload($question->definition, null, revealCorrect: true),
        ]);
    }
}
