<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use App\Support\SafeCsv;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Per-test results as CSV (owner / admin), streamed: student, email,
 * group(s), score, %, passed, submitted at and anti-cheat counts. Every cell
 * goes through SafeCsv (formula injection, UTF-8 BOM). Real, finished
 * attempts only. Queued exports for very large tests come in Stage 12.
 */
final class TestResultsExportController extends Controller
{
    public const int CHUNK = 200;

    /**
     * @return list<string>
     */
    private static function header(): array
    {
        return array_map(fn (string $key): string => trans($key), ['Student', 'Email', 'Groups', 'Score', 'Max score', 'Percent', 'Passed', 'Submitted', 'Integrity events']);
    }

    public function show(Test $test): StreamedResponse
    {
        Gate::authorize('viewAnalytics', $test);

        $groups = $test->groups()->with('members:id')->get(['groups.id', 'groups.name']);
        $filename = Str::slug($test->title) ?: 'test';

        return response()->streamDownload(function () use ($test, $groups): void {
            echo SafeCsv::BOM.SafeCsv::line([
                ...self::header(),
            ]);

            Attempt::query()->real()
                ->where('test_id', $test->id)
                ->whereNotNull('submitted_at')
                ->with('user:id,name,email')
                ->withCount('events')
                ->orderBy('submitted_at')
                ->chunkById(self::CHUNK, function ($attempts) use ($groups): void {
                    foreach ($attempts as $attempt) {
                        /** @var Attempt $attempt */
                        echo SafeCsv::line([
                            $attempt->user->name,
                            $attempt->user->email,
                            $groups->filter(fn (Group $group): bool => $group->members->contains('id', $attempt->user_id))->pluck('name')->implode(', '),
                            $attempt->score,
                            $attempt->max_score,
                            $attempt->score_percent,
                            // 1 = passed, 0 = failed, empty = not graded yet.
                            $attempt->passed,
                            $attempt->submitted_at?->toIso8601String(),
                            (int) $attempt->getAttribute('events_count') + array_sum($attempt->event_overflow ?? []),
                        ]);
                    }
                });
        }, "{$filename}-results.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
