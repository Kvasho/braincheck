<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Grading\GradeAnswerManually;
use App\Domain\Grading\AnonymousLabels;
use App\Domain\Grading\GradingQueue;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ManualGradeRequest;
use App\Http\Resources\GradingAnswerResource;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The grading screen of one answer (attempt + position: no answer IDs in
 * URLs), and saving a manual grade ("save & next").
 */
final class GradingAnswerController extends Controller
{
    public function show(Request $request, Attempt $attempt, int $position): Response
    {
        Gate::authorize('grade', $attempt);
        abort_if($attempt->status === AttemptStatus::InProgress, 404);

        $user = $request->user() ?? abort(403);
        $question = AttemptQuestion::query()->where('attempt_id', $attempt->id)->where('position', $position)->with(['answer', 'attempt'])->firstOrFail();
        $attempt->load(['test:id,ulid,title', 'user:id,name']);
        $anonymous = (bool) $request->session()->get(AnonymousLabels::SESSION_KEY, true);
        $number = AnonymousLabels::forTests([$attempt->test_id])[$attempt->test_id][$attempt->user_id] ?? 0;
        $next = GradingQueue::next($user, $question);

        return Inertia::render('teacher/grading/answer', [
            'answer' => (new GradingAnswerResource($question))->resolve($request),
            'attempt' => [
                'ulid' => $attempt->ulid,
                'test' => ['ulid' => $attempt->test->ulid, 'title' => $attempt->test->title],
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'status' => $attempt->status->value,
                'question_count' => $attempt->questions()->count(),
            ],
            'student' => [
                'label' => __('Student #:number', ['number' => $number]),
                'name' => $anonymous ? null : $attempt->user->name,
            ],
            'anonymous' => $anonymous,
            'next' => $next === null ? null : ['attempt' => $next->attemptQuestion->attempt->ulid, 'position' => $next->attemptQuestion->position],
        ]);
    }

    public function update(ManualGradeRequest $request, Attempt $attempt, int $position, GradeAnswerManually $grade): RedirectResponse
    {
        $user = $request->user() ?? abort(403);
        $question = $request->question();

        $grade->handle($question, $request->points(), $request->feedback(), $user, $request->string('version')->toString());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Grade saved.')]);

        $next = $request->boolean('next') ? GradingQueue::next($user, $question) : null;

        return $next === null
            ? ($request->boolean('next') ? to_route('teacher.grading.index') : to_route('teacher.grading.answers.show', ['attempt' => $attempt, 'position' => $position]))
            : to_route('teacher.grading.answers.show', ['attempt' => $next->attemptQuestion->attempt, 'position' => $next->attemptQuestion->position]);
    }
}
