<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\Attempts\RecordAttemptEvents;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\RecordAttemptEventsRequest;
use App\Models\Attempt;
use Illuminate\Http\Response;

/**
 * Anti-cheat event batches (JSON, rate limited by `attempt-events`).
 */
final class AttemptEventController extends Controller
{
    public function store(RecordAttemptEventsRequest $request, Attempt $attempt, RecordAttemptEvents $record): Response
    {
        $record->handle($attempt, $request->events());

        return response()->noContent();
    }
}
