<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionPreviewResource;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Preview as student": the Player with toPlayerPayload(), the code path
 * students use. Checking an answer goes to QuestionPreviewGradeController.
 */
final class QuestionPreviewController extends Controller
{
    public function show(Request $request, Question $question): Response
    {
        Gate::authorize('preview', $question);

        return Inertia::render('teacher/questions/preview', [
            'question' => (new QuestionPreviewResource($question))->resolve($request),
            'canGrade' => $request->user()?->can('previewGrade', $question) === true,
        ]);
    }
}
