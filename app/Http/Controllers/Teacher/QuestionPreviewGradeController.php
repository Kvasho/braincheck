<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Domain\Questions\Points;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\PreviewGradeRequest;
use App\Models\Question;
use Illuminate\Http\JsonResponse;

/**
 * Grades a preview answer (owner only, rate limited). The result reveals the
 * correct answer, which is fine: only the owner can call it.
 */
final class QuestionPreviewGradeController extends Controller
{
    public function store(PreviewGradeRequest $request, Question $question, QuestionTypeRegistry $registry): JsonResponse
    {
        $handler = $registry->for($question->type);
        $answer = $request->answer();

        return response()->json([
            'result' => $handler->grade($question->definition, $answer, Points::of($question->points))->toArray(),
            'review' => $handler->toReviewPayload($question->definition, $answer, revealCorrect: true),
            'explanation' => $question->explanation?->toArray(),
        ]);
    }
}
