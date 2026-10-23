<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Actions\Grading\RegradeTestQuestion;
use App\Domain\Grading\SnapshotGrader;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Domain\Shared\RichTextDocument;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\RegradeRequest;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Test;
use App\Support\QuestionListing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Regrade a test question (owner in the teacher area, admins in the admin
 * area: same controller, TestPolicy::regrade). The page lists the question
 * versions used by the test's attempts; starting a regrade needs password
 * confirmation (route middleware) and is audited and queued.
 */
final class TestRegradeController extends Controller
{
    public function show(Request $request, Test $test, QuestionTypeRegistry $registry): Response
    {
        Gate::authorize('regrade', $test);

        $versions = AttemptQuestion::query()
            ->whereIn('attempt_id', Attempt::query()->real()->where('test_id', $test->id)->select('id'))
            ->whereNotNull('question_id')
            ->orderBy('id')
            ->get(['id', 'attempt_id', 'question_id', 'position', 'points', 'snapshot'])
            ->groupBy(fn (AttemptQuestion $row): string => $row->question_id.':'.(int) $row->snapshot['version'])
            ->map(function ($rows) use ($registry): array {
                /** @var AttemptQuestion $sample */
                $sample = $rows->first();
                $type = SnapshotGrader::type($sample);

                return [
                    'question' => (string) $sample->snapshot['question_ulid'],
                    'version' => (int) $sample->snapshot['version'],
                    'type' => $type->value,
                    'excerpt' => Str::limit(RichTextDocument::fromArray((array) $sample->snapshot['body'])->plainText, 140),
                    'uses' => $rows->count(),
                    'definition' => $registry->for($type)->toEditorInput(SnapshotGrader::definition($sample)),
                ];
            })
            ->values()
            ->all();

        $area = $request->routeIs('admin.*') ? 'admin' : 'teacher';

        return Inertia::render('grading/regrade', [
            'test' => ['ulid' => $test->ulid, 'title' => $test->title],
            'versions' => $versions,
            'scorings' => QuestionListing::options()['scorings'],
            'urls' => [
                'preview' => route("{$area}.tests.regrade.preview", $test),
                'store' => route("{$area}.tests.regrade.store", $test),
            ],
        ]);
    }

    public function store(RegradeRequest $request, Test $test, RegradeTestQuestion $regrade): RedirectResponse
    {
        $summary = $regrade->handle($request->user() ?? abort(403), $test, $request->questionId(), $request->version(), $request->definition());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Regrade started for :count attempt(s). Scores update in a moment.', ['count' => $summary['affected']])]);

        return to_route($request->routeIs('admin.*') ? 'admin.tests.regrade.show' : 'teacher.tests.regrade.show', $test);
    }
}
