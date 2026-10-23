<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\StartPreviewAttempt;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Test;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Preview as student": start a preview attempt (store) and show it in the
 * exam player (show). Simplified player until the Stage 7 exam engine.
 */
final class TestPreviewController extends Controller
{
    public function store(Request $request, Test $test, StartPreviewAttempt $startPreview): RedirectResponse
    {
        Gate::authorize('preview', $test);

        $attempt = $startPreview->handle($test, $request->user() ?? abort(403));

        return to_route('teacher.tests.preview.show', ['test' => $test, 'attempt' => $attempt]);
    }

    public function show(Request $request, Test $test, Attempt $attempt): Response
    {
        Gate::authorize('preview', $test);

        // Only the teacher's own preview of this test.
        abort_unless($attempt->is_preview && $attempt->test_id === $test->id && $attempt->user_id === $request->user()?->id, 404);

        $attempt->load('questions');

        return Inertia::render('exam/test-preview', [
            'test' => [
                'ulid' => $test->ulid,
                'title' => $test->title,
                'time_limit_minutes' => $test->time_limit_minutes,
            ],
            'attempt' => [
                'ulid' => $attempt->ulid,
                'status' => $attempt->status->value,
                'deadline_at' => $attempt->deadline_at?->toIso8601String(),
            ],
            // The student view of each snapshot: body, image and player payload only.
            'questions' => $attempt->questions->map(fn (AttemptQuestion $row): array => [
                'position' => $row->position,
                'type' => $row->snapshot['type'],
                'points' => $row->points,
                'body' => $row->snapshot['body'],
                // Built from the snapshot (no query per question).
                'image_url' => is_string($row->snapshot['image_path'] ?? null)
                    ? route('question-images.show', ['question' => $row->snapshot['question_ulid'], 'v' => substr(hash('sha256', $row->snapshot['image_path']), 0, 12)])
                    : null,
                'player' => $row->snapshot['player'],
            ])->values()->all(),
        ]);
    }
}
