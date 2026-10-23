<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Support\QuestionImageStore;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * The image of a question in the student's own attempt, addressed by
 * position (no question ID reaches the client). Read from the snapshot.
 */
final class AttemptImageController extends Controller
{
    public function show(Attempt $attempt, int $position, QuestionImageStore $images): Response
    {
        abort_unless(Gate::allows('take', $attempt), 404);

        $question = AttemptQuestion::query()->where('attempt_id', $attempt->id)->where('position', $position)->firstOrFail();
        $path = $question->snapshot['image_path'] ?? null;

        abort_unless(is_string($path) && QuestionImageStore::isStoredPath($path) && $images->disk()->exists($path), 404);

        return response((string) $images->disk()->get($path), 200, [
            'Content-Type' => 'image/webp',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="question.webp"',
        ]);
    }
}
