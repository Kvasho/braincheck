<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Grading\RegradeTestQuestion;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\RegradeRequest;
use App\Models\Test;
use Illuminate\Http\JsonResponse;

/**
 * What a regrade would change (JSON): affected attempts with old and new
 * scores and pass results. Writes nothing.
 */
final class TestRegradePreviewController extends Controller
{
    public function store(RegradeRequest $request, Test $test, RegradeTestQuestion $regrade): JsonResponse
    {
        return response()->json($regrade->preview($test, $request->questionId(), $request->version(), $request->definition()));
    }
}
