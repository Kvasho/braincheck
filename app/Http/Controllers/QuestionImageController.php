<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Question;
use App\Support\QuestionImageStore;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a question's image from the private disk after authorization
 * (QuestionPolicy::viewImage). The file is looked up by the question's ULID,
 * never by a path from the request, so path traversal is impossible.
 */
final class QuestionImageController extends Controller
{
    public function show(Question $question, QuestionImageStore $images): Response
    {
        Gate::authorize('viewImage', $question);

        $path = $question->image_path;

        abort_unless(QuestionImageStore::isStoredPath($path) && $images->disk()->exists((string) $path), 404);

        return response((string) $images->disk()->get((string) $path), 200, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="question.webp"',
        ]);
    }
}
