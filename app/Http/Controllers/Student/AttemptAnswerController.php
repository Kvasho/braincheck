<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Actions\Attempts\SaveAnswer;
use App\Domain\Attempts\AttemptClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveAnswerRequest;
use App\Models\Attempt;
use Illuminate\Http\JsonResponse;

/**
 * Autosave (JSON). Rate limited by `autosave`; answers only `{ saved_at }`.
 */
final class AttemptAnswerController extends Controller
{
    public function update(SaveAnswerRequest $request, Attempt $attempt, int $position, SaveAnswer $saveAnswer): JsonResponse
    {
        $savedAt = $saveAnswer->handle($attempt, $position, $request->update(), AttemptClient::fromRequest($request)->sessionHash);

        return response()->json(['saved_at' => $savedAt->toIso8601String()]);
    }
}
