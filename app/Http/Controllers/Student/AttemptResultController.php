<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Domain\Attempts\StudentExams;
use App\Domain\Tests\AvailableTests;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\StudentAttemptResultResource;
use App\Models\Attempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The student's result of one attempt (owner only; anyone else: 404), cut
 * by ResultsVisibilityPolicy. Retry is offered while the test is still
 * available to the student with attempts left.
 */
final class AttemptResultController extends Controller
{
    public function show(Request $request, Attempt $attempt, StudentExams $exams): Response|RedirectResponse
    {
        abort_unless(Gate::allows('view', $attempt) && ! $attempt->is_preview, 404);

        if ($attempt->status === AttemptStatus::InProgress) {
            return to_route('student.attempts.show', $attempt);
        }

        $user = $request->user() ?? abort(403);
        $attempt->load(['test', 'questions.answer']);

        $available = AvailableTests::forStudent($user)->whereKey($attempt->test_id)->with('owner:id,name')->withCount('questions')->first();
        $canRetry = $available !== null && $exams->card($available, student: $user)['section'] === StudentExams::AVAILABLE;

        return Inertia::render('student/attempts/result', [
            'attempt' => (new StudentAttemptResultResource($attempt))->resolve($request),
            'retry' => $canRetry ? $attempt->test->ulid : null,
        ]);
    }
}
