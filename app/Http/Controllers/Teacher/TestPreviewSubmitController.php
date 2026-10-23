<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Tests\SubmitPreviewAttempt;
use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\SubmitPreviewRequest;
use App\Models\Attempt;
use App\Models\Test;
use Illuminate\Http\JsonResponse;

/**
 * Finishes a preview and returns the results with the full review (the
 * teacher owns the test, so revealing the answers is fine).
 */
final class TestPreviewSubmitController extends Controller
{
    public function store(SubmitPreviewRequest $request, Test $test, Attempt $attempt, SubmitPreviewAttempt $submit): JsonResponse
    {
        abort_unless($attempt->is_preview && $attempt->test_id === $test->id && $attempt->user_id === $request->user()?->id, 404);
        abort_if($attempt->status !== AttemptStatus::InProgress, 409, __('This preview is already finished.'));

        return response()->json($submit->handle($attempt, $request->answers()));
    }
}
