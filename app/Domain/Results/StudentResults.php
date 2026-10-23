<?php

declare(strict_types=1);

namespace App\Domain\Results;

use App\Domain\Analytics\Statistics;
use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A student's finished real attempts, and the charts built ONLY from the
 * results they may see (ResultsVisibilityPolicy): progress over time and
 * strengths by category.
 */
final readonly class StudentResults
{
    public function __construct(private ResultsVisibilityPolicy $policy) {}

    /**
     * @return Builder<Attempt>
     */
    public static function finished(User $student): Builder
    {
        return Attempt::query()->real()
            ->where('user_id', $student->id)
            ->where('status', '!=', AttemptStatus::InProgress->value)
            ->with(['test' => fn ($query) => $query->withTrashed()->with('category')]);
    }

    /**
     * Visible results, oldest first.
     *
     * @return Collection<int, Attempt>
     */
    public function visible(User $student): Collection
    {
        return self::finished($student)
            ->orderBy('submitted_at')
            ->get()
            ->filter(fn (Attempt $attempt): bool => $this->policy->canSeeScore($attempt))
            ->values();
    }

    /**
     * @param  Collection<int, Attempt>  $visible
     * @return list<array{date: string|null, percent: string, test: string}>
     */
    public static function progress(Collection $visible): array
    {
        return array_values($visible->map(fn (Attempt $attempt): array => [
            'date' => $attempt->submitted_at?->toIso8601String(),
            'percent' => (string) $attempt->score_percent,
            'test' => $attempt->test->title,
        ])->all());
    }

    /**
     * Average % per test category (tests without a category: `category` null).
     *
     * @param  Collection<int, Attempt>  $visible
     * @return list<array{category: array{ka: string, en: string}|null, average: string, count: int}>
     */
    public static function strengths(Collection $visible): array
    {
        return array_values($visible
            ->groupBy(fn (Attempt $attempt): string => (string) ($attempt->test->category_id ?? 'none'))
            ->map(function (Collection $attempts): array {
                /** @var Attempt $first */
                $first = $attempts->first();
                $percents = array_values($attempts->map(fn (Attempt $attempt): string => (string) $attempt->score_percent)->all());

                return [
                    'category' => $first->test->category?->name->toArray(),
                    'average' => (string) Statistics::mean($percents),
                    'count' => count($percents),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['average'])
            ->all());
    }
}
