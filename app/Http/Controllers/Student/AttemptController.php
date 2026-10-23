<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\Attempts\FinishExpiredAttempt;
use App\Actions\Attempts\StartAttempt;
use App\Domain\Attempts\AttemptClient;
use App\Domain\Tests\AvailableTests;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlayerAttemptResource;
use App\Models\Attempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Start (or resume) an attempt, and the exam player.
 */
final class AttemptController extends Controller
{
    public function store(Request $request, string $test, StartAttempt $startAttempt): RedirectResponse
    {
        $user = $request->user() ?? abort(403);

        // Not visible: 404, like the intro page.
        $found = AvailableTests::forStudent($user)->where('ulid', $test)->firstOrFail();

        ['attempt' => $attempt, 'resumed' => $resumed] = $startAttempt->handle($user, $found, AttemptClient::fromRequest($request));

        if ($resumed) {
            Inertia::flash('toast', ['type' => 'info', 'message' => __('You continue your attempt in progress.')]);
        }

        return to_route('student.attempts.show', $attempt);
    }

    /**
     * The player: only the owner's real attempt in progress. A finished (or
     * just expired) attempt goes to its result page.
     */
    public function show(Request $request, Attempt $attempt, FinishExpiredAttempt $finishExpired): Response|RedirectResponse
    {
        abort_unless(Gate::allows('take', $attempt), 404);

        $finishExpired->handle($attempt);

        if ($attempt->status !== AttemptStatus::InProgress) {
            return to_route('student.attempts.result', $attempt);
        }

        $attempt->load(['test:id,title', 'questions.answer']);

        return Inertia::render('exam/attempt', [
            'attempt' => (new PlayerAttemptResource($attempt))->resolve($request),
        ]);
    }
}
