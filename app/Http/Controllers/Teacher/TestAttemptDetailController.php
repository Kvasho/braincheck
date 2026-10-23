<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Grading\SnapshotGrader;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use App\Models\Test;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One attempt in the test analytics (owner / admin): every answer with the
 * full review (correct answers revealed) and the anti-cheat timeline with
 * server times relative to the start.
 */
final class TestAttemptDetailController extends Controller
{
    public function show(Request $request, Test $test, Attempt $attempt, QuestionTypeRegistry $registry): Response
    {
        Gate::authorize('viewAnalytics', $test);
        abort_unless($attempt->test_id === $test->id && ! $attempt->is_preview, 404);

        $attempt->load(['user:id,name,email', 'questions.answer', 'events']);

        return Inertia::render('analytics/attempt', [
            'test' => ['ulid' => $test->ulid, 'title' => $test->title],
            'area' => $request->routeIs('admin.*') ? 'admin' : 'teacher',
            'attempt' => [
                'ulid' => $attempt->ulid,
                'student' => ['name' => $attempt->user->name, 'email' => $attempt->user->email],
                'status' => $attempt->status->value,
                'grading_status' => $attempt->grading_status->value,
                'score' => $attempt->score,
                'max_score' => $attempt->max_score,
                'percent' => $attempt->score_percent,
                'passed' => $attempt->passed,
                'started_at' => $attempt->started_at->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'event_overflow' => $attempt->event_overflow ?? [],
            ],
            'questions' => $attempt->questions->map(fn (AttemptQuestion $question): array => [
                'position' => $question->position,
                'type' => (string) $question->snapshot['type'],
                'body' => $question->snapshot['body'],
                'points' => $question->points,
                'awarded_points' => $question->answer?->awarded_points,
                'review' => $registry->for(SnapshotGrader::type($question))
                    ->toReviewPayload(SnapshotGrader::definition($question), $question->answer?->answer, revealCorrect: true),
                'feedback' => $question->answer?->feedback,
            ])->values()->all(),
            'timeline' => $attempt->events->map(fn (AttemptEvent $event): array => [
                'type' => $event->type->value,
                'label' => $event->type->label(),
                'seconds' => (int) $attempt->started_at->diffInSeconds($event->occurred_at, false),
                'meta' => $event->meta,
            ])->values()->all(),
        ]);
    }
}
