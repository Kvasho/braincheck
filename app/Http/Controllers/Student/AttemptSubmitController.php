<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\Attempts\SubmitAttempt;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

final class AttemptSubmitController extends Controller
{
    public function store(Attempt $attempt, SubmitAttempt $submitAttempt): RedirectResponse
    {
        abort_unless(Gate::allows('take', $attempt), 404);

        $attempt = $submitAttempt->handle($attempt);

        Inertia::flash('toast', ['type' => 'success', 'message' => $attempt->status === AttemptStatus::TimedOut
            ? __('Time was up. Your answers up to the deadline were submitted.')
            : __('Your answers were submitted.')]);

        return to_route('student.attempts.result', $attempt);
    }
}
