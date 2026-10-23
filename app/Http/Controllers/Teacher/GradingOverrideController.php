<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Grading\OverrideAnswerScore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\OverrideScoreRequest;
use App\Models\Attempt;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Override an auto-graded answer's points (test owner, reason required, audited).
 */
final class GradingOverrideController extends Controller
{
    public function update(OverrideScoreRequest $request, Attempt $attempt, int $position, OverrideAnswerScore $override): RedirectResponse
    {
        $override->handle($request->question(), $request->points(), $request->reason(), $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Points changed. The change is recorded in the audit log.')]);

        return to_route('teacher.grading.answers.show', ['attempt' => $attempt, 'position' => $position]);
    }
}
