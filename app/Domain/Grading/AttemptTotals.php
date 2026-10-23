<?php

declare(strict_types=1);

namespace App\Domain\Grading;

use App\Domain\Questions\Points;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * An attempt's totals from its answer rows (Points maths, no floats):
 *
 * - max_score = Σ snapshot points;
 * - score = Σ awarded points (an ungraded answer counts 0);
 * - score_percent = score / max × 100, scale 2, half-up (0 when max is 0);
 * - pending = some answer still needs manual grading;
 * - passed = score_percent ≥ pass_percent (equality passes), null while pending.
 */
final readonly class AttemptTotals
{
    private function __construct(
        public Points $score,
        public Points $max,
        public BigDecimal $percent,
        public bool $pending,
    ) {}

    /**
     * Requires `questions.answer`.
     */
    public static function of(Attempt $attempt): self
    {
        $score = Points::zero();
        $max = Points::zero();
        $pending = false;

        foreach ($attempt->questions as $question) {
            /** @var AttemptQuestion $question */
            $max = $max->plus(Points::of($question->points));
            $answer = $question->answer;

            if ($answer === null) {
                continue;
            }

            if ($answer->awarded_points !== null) {
                $score = $score->plus(Points::of($answer->awarded_points));
            }

            $pending = $pending || ($answer->needs_manual_grading && $answer->graded_at === null);
        }

        return new self($score, $max, self::percent($score, $max), $pending);
    }

    public static function percent(Points $score, Points $max): BigDecimal
    {
        return $max->isZero()
            ? BigDecimal::zero()->toScale(Points::SCALE)
            : $score->value->multipliedBy(100)->dividedBy($max->value, Points::SCALE, RoundingMode::HalfUp);
    }

    public function passes(int $passPercent): ?bool
    {
        return $this->pending ? null : $this->percent->isGreaterThanOrEqualTo($passPercent);
    }

    /**
     * The attempt columns these totals set.
     *
     * @return array{score: string, max_score: string, score_percent: string}
     */
    public function columns(): array
    {
        return [
            'score' => $this->score->toString(),
            'max_score' => $this->max->toString(),
            'score_percent' => $this->percent->toString(),
        ];
    }
}
