<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Questions\BulkArchiveQuestions;
use App\Actions\Questions\BulkTagQuestions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\BulkQuestionsRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Bulk actions on the teacher's own questions (ownership is checked for every
 * ULID before anything happens: see BulkQuestionsRequest).
 */
final class QuestionBulkController extends Controller
{
    public function store(BulkQuestionsRequest $request, BulkTagQuestions $bulkTag, BulkArchiveQuestions $bulkArchive): RedirectResponse
    {
        $questions = $request->questions();

        match ($request->string('action')->toString()) {
            'tag' => $bulkTag->handle($questions, $request->tags(), attach: true),
            'untag' => $bulkTag->handle($questions, $request->tags(), attach: false),
            default => $bulkArchive->handle($questions),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => trans_choice(':count question updated.|:count questions updated.', count($questions), ['count' => count($questions)])]);

        return back();
    }
}
