<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Grading\GradingQueue;
use App\Enums\GradingStatus;
use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\TestQuestionStat;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The teacher dashboard: the teacher's own tests and their real attempts only.
 */
final class TeacherDashboard
{
    public const int LIST_LIMIT = 5;

    public const int CLOSING_WITHIN_DAYS = 7;

    /**
     * @return array<string, mixed>
     */
    public static function for(User $teacher): array
    {
        $now = CarbonImmutable::now();
        $ownTests = Test::query()->where('owner_id', $teacher->id)->select('id');
        $graded = Attempt::query()->real()->whereIn('test_id', $ownTests)->where('grading_status', GradingStatus::Graded->value);
        $gradedCount = (clone $graded)->count();

        return [
            'cards' => [
                'active_tests' => Test::query()->where('owner_id', $teacher->id)->published()->openAt($now)->count(),
                'submissions_week' => Attempt::query()->real()->whereIn('test_id', $ownTests)->where('submitted_at', '>=', $now->subDays(7))->count(),
                'pending_grading' => GradingQueue::pending($teacher)->count(),
                'pass_rate' => $gradedCount === 0 ? null : Statistics::share((clone $graded)->where('passed', true)->count(), $gradedCount),
            ],
            'closing_soon' => array_values(Test::query()->where('owner_id', $teacher->id)->published()
                ->whereBetween('closes_at', [$now, $now->addDays(self::CLOSING_WITHIN_DAYS)])
                ->orderBy('closes_at')
                ->limit(self::LIST_LIMIT)
                ->get(['ulid', 'title', 'closes_at'])
                ->map(fn (Test $test): array => ['ulid' => $test->ulid, 'title' => $test->title, 'closes_at' => $test->closes_at?->toIso8601String()])
                ->all()),
            'recent' => array_values(Attempt::query()->real()->whereIn('test_id', $ownTests)
                ->whereNotNull('submitted_at')
                ->with(['user:id,name', 'test:id,ulid,title'])
                ->latest('submitted_at')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (Attempt $attempt): array => [
                    'attempt' => $attempt->ulid,
                    'test' => ['ulid' => $attempt->test->ulid, 'title' => $attempt->test->title],
                    'student' => $attempt->user->name,
                    'percent' => $attempt->grading_status === GradingStatus::Graded ? $attempt->score_percent : null,
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                ])
                ->all()),
            'needs_review' => array_values(TestQuestionStat::query()
                ->whereIn('test_id', Test::query()->where('owner_id', $teacher->id)->where('status', '!=', TestStatus::Archived->value)->select('id'))
                ->where('needs_review', true)
                ->with('test:id,ulid,title')
                ->orderBy('discrimination')
                ->limit(self::LIST_LIMIT)
                ->get()
                ->map(fn (TestQuestionStat $stat): array => [
                    'test' => ['ulid' => $stat->test->ulid, 'title' => $stat->test->title],
                    'excerpt' => $stat->excerpt,
                    'discrimination' => $stat->discrimination,
                ])
                ->all()),
        ];
    }
}
