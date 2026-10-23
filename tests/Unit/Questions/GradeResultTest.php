<?php

declare(strict_types=1);

use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;

it('describes full, zero, partial and pending results', function (): void {
    $max = Points::of('2');

    expect(GradeResult::full($max)->toArray())->toBe(['is_correct' => true, 'awarded_points' => '2.00', 'needs_manual_grading' => false])
        ->and(GradeResult::zero()->toArray())->toBe(['is_correct' => false, 'awarded_points' => '0.00', 'needs_manual_grading' => false])
        ->and(GradeResult::partial(Points::of('1.33'), $max)->toArray())->toBe(['is_correct' => false, 'awarded_points' => '1.33', 'needs_manual_grading' => false])
        ->and(GradeResult::partial(Points::of('2'), $max)->isCorrect)->toBeTrue()
        ->and(GradeResult::pendingManualGrading()->toArray())->toBe(['is_correct' => null, 'awarded_points' => '0.00', 'needs_manual_grading' => true]);
});

it('never awards more than the maximum', function (): void {
    expect(fn () => GradeResult::partial(Points::of('3'), Points::of('2')))->toThrow(InvalidArgumentException::class);
});
