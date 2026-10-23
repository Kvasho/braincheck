<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

use App\Actions\Attempts\FinishExpiredAttempt;
use App\Domain\Tests\AvailableTests;
use App\Enums\AttemptStatus;
use App\Enums\QuestionSelectionMode;
use App\Enums\ResultsVisibility;
use App\Models\Attempt;
use App\Models\Group;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The student's exams, sorted into sections. Tests come only from
 * AvailableTests; attempts only from the student's own real attempts.
 */
final readonly class StudentExams
{
    public const string AVAILABLE = 'available';

    public const string IN_PROGRESS = 'in_progress';

    public const string UPCOMING = 'upcoming';

    public const string CLOSED = 'closed';

    public function __construct(private FinishExpiredAttempt $finishExpired) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function cards(User $student, ?Group $inGroup = null): array
    {
        $tests = AvailableTests::forStudent($student, $inGroup)
            ->with([
                'owner:id,name',
                'groups' => fn ($groups) => $groups->whereHas('members', fn (Builder $members) => $members->whereKey($student->id))->select(['groups.id', 'groups.name']),
            ])
            ->withCount('questions')
            ->orderBy('title')
            ->get();

        $attempts = $this->attempts($student, $tests->pluck('id')->all());

        return array_values($tests
            ->map(fn (Test $test): array => $this->card($test, $attempts->get($test->id, collect())))
            ->all());
    }

    /**
     * One test's card (the intro page uses it too).
     *
     * @param  Collection<int, Attempt>|null  $attempts  the student's real attempts on $test
     * @return array<string, mixed>
     */
    public function card(Test $test, ?Collection $attempts = null, ?User $student = null): array
    {
        if ($attempts === null && $student !== null) {
            $attempts = $this->attempts($student, [$test->id])->get($test->id, collect());
        }

        $attempts ??= collect();
        $now = CarbonImmutable::now();
        $inProgress = $attempts->first(fn (Attempt $attempt): bool => $attempt->status === AttemptStatus::InProgress);
        $used = $attempts->count();
        $closed = $test->closes_at !== null && $now->greaterThanOrEqualTo($test->closes_at);
        $exhausted = $test->max_attempts !== null && $used >= $test->max_attempts;

        $section = match (true) {
            $inProgress !== null => self::IN_PROGRESS,
            $test->opens_at !== null && $now->lessThan($test->opens_at) => self::UPCOMING,
            $closed || $exhausted => self::CLOSED,
            default => self::AVAILABLE,
        };

        $showScore = match ($test->results_visibility) {
            ResultsVisibility::Immediately => true,
            ResultsVisibility::AfterClose => $closed,
            default => false,
        };

        $best = $showScore
            ? $attempts->whereNotNull('score_percent')->max(fn (Attempt $attempt): float => (float) $attempt->score_percent)
            : null;

        return [
            'ulid' => $test->ulid,
            'title' => $test->title,
            'description' => $test->description,
            'teacher' => $test->owner->name,
            'groups' => $test->relationLoaded('groups') ? $test->groups->pluck('name')->values()->all() : [],
            'time_limit_minutes' => $test->time_limit_minutes,
            'question_count' => $test->selection_mode === QuestionSelectionMode::RandomPool
                ? min((int) $test->random_question_count, (int) $test->questions_count)
                : (int) $test->questions_count,
            'pass_percent' => $test->pass_percent,
            'attempts_used' => $used,
            'max_attempts' => $test->max_attempts,
            'opens_at' => $test->opens_at?->toIso8601String(),
            'closes_at' => $test->closes_at?->toIso8601String(),
            'section' => $section,
            'attempt' => $inProgress === null ? null : [
                'ulid' => $inProgress->ulid,
                'deadline_at' => $inProgress->deadline_at?->toIso8601String(),
            ],
            'best_percent' => $best === null ? null : number_format((float) $best, 2, '.', ''),
        ];
    }

    /**
     * The student's real attempts on $testIds, grouped by test. Expired
     * in-progress attempts are finished first (lazy expiry).
     *
     * @param  array<int, mixed>  $testIds
     * @return Collection<int, Collection<int, Attempt>>
     */
    private function attempts(User $student, array $testIds): Collection
    {
        $query = fn () => Attempt::query()->real()
            ->where('user_id', $student->id)
            ->whereIn('test_id', $testIds)
            ->get(['id', 'ulid', 'test_id', 'user_id', 'is_preview', 'status', 'deadline_at', 'score_percent']);

        $attempts = $query();
        $expired = $attempts->filter(fn (Attempt $attempt): bool => AttemptTiming::isExpired($attempt));

        if ($expired->isNotEmpty()) {
            $expired->each(fn (Attempt $attempt): bool => $this->finishExpired->handle($attempt));
            $attempts = $query();
        }

        /** @var Collection<int, Collection<int, Attempt>> $grouped */
        $grouped = $attempts->groupBy('test_id');

        return $grouped;
    }
}
