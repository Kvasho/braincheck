<?php

declare(strict_types=1);

namespace App\Domain\Questions;

use InvalidArgumentException;

/**
 * The outcome of grading one answer.
 *
 * - isCorrect: true (full points), false (anything less), null (not decided yet:
 *   waiting for a teacher).
 * - A result waiting for manual grading always has 0 awarded points.
 */
final readonly class GradeResult
{
    private function __construct(
        public ?bool $isCorrect,
        public Points $awardedPoints,
        public bool $needsManualGrading,
    ) {}

    public static function full(Points $points): self
    {
        return new self(true, $points, false);
    }

    public static function zero(): self
    {
        return new self(false, Points::zero(), false);
    }

    /**
     * Partial credit: correct only if it reaches the maximum.
     *
     * @throws InvalidArgumentException if awarded exceeds the maximum
     */
    public static function partial(Points $awarded, Points $maximum): self
    {
        if ($awarded->isGreaterThan($maximum)) {
            throw new InvalidArgumentException('Awarded points can not exceed the maximum.');
        }

        return new self($awarded->equals($maximum), $awarded, false);
    }

    /**
     * A teacher has to grade this answer (free text).
     */
    public static function pendingManualGrading(): self
    {
        return new self(null, Points::zero(), true);
    }

    /**
     * @return array{is_correct: bool|null, awarded_points: string, needs_manual_grading: bool}
     */
    public function toArray(): array
    {
        return [
            'is_correct' => $this->isCorrect,
            'awarded_points' => $this->awardedPoints->toString(),
            'needs_manual_grading' => $this->needsManualGrading,
        ];
    }
}
