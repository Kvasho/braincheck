<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\NumericDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Enums\QuestionType;
use Tests\Support\PayloadLeakScanner;
use Tests\Support\QuestionHandlers;

describe('true_false', function (): void {
    $type = QuestionType::TrueFalse;

    it('validates the definition', function () use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, ['correct' => false]))->toBe([])
            ->and(QuestionHandlers::definitionErrors($type, []))->toHaveKey('correct')
            ->and(QuestionHandlers::definitionErrors($type, ['correct' => 'maybe']))->toHaveKey('correct');
    });

    it('grades full or zero', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['correct' => false]);
        $handler = QuestionHandlers::for($type);
        $points = Points::of('1');

        expect($definition)->toEqual(new TrueFalseDefinition(false))
            ->and($handler->grade($definition, ['value' => false], $points)->isCorrect)->toBeTrue()
            ->and($handler->grade($definition, ['value' => true], $points)->awardedPoints->toString())->toBe('0.00')
            ->and($handler->grade($definition, ['value' => null], $points)->isCorrect)->toBeFalse()
            ->and($handler->grade($definition, null, $points)->isCorrect)->toBeFalse();
    });

    it('validates answers', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['correct' => true]);

        expect(QuestionHandlers::answerErrors($definition, ['value' => true]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['value' => 'yes']))->toHaveKey('value')
            ->and(QuestionHandlers::answerErrors($definition, ['value' => [true]]))->toHaveKey('value')
            ->and(QuestionHandlers::answerErrors($definition, []))->toHaveKey('value');
    });
});

describe('numeric', function (): void {
    $type = QuestionType::Numeric;

    it('rejects every invalid definition', function (array $input, string $field) use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, $input))->toHaveKey($field);
    })->with([
        'missing correct' => [['tolerance' => '0'], 'correct'],
        'float, not a string' => [['correct' => 9.81, 'tolerance' => '0'], 'correct'],
        'exponent' => [['correct' => '1e3', 'tolerance' => '0'], 'correct'],
        'text' => [['correct' => 'nine', 'tolerance' => '0'], 'correct'],
        'NaN' => [['correct' => 'NaN', 'tolerance' => '0'], 'correct'],
        'negative tolerance' => [['correct' => '1', 'tolerance' => '-0.1'], 'tolerance'],
        'unit too long' => [['correct' => '1', 'tolerance' => '0', 'unit' => str_repeat('m', 33)], 'unit'],
    ]);

    it('normalizes decimals to canonical strings', function () use ($type): void {
        /** @var NumericDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['correct' => '-9,810', 'tolerance' => '0.05', 'unit' => ' m/s² ']);

        expect($definition->toArray())->toBe(['correct' => '-9.810', 'tolerance' => '0.05', 'unit' => 'm/s²']);
    });

    it('grades |value − correct| ≤ tolerance exactly, with no float drift', function (string $correct, string $tolerance, ?string $value, bool $expected) use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['correct' => $correct, 'tolerance' => $tolerance]);
        $result = QuestionHandlers::for($type)->grade($definition, ['value' => $value], Points::of('1'));

        expect($result->isCorrect)->toBe($expected);
    })->with([
        'exact' => ['9.81', '0', '9.81', true],
        'trailing zeros' => ['9.81', '0', '9.810', true],
        'upper boundary' => ['9.81', '0.05', '9.86', true],
        'lower boundary' => ['9.81', '0.05', '9.76', true],
        'just outside' => ['9.81', '0.05', '9.861', false],
        'float drift case 0.1 + 0.2' => ['0.3', '0', '0.30', true],
        'decimal comma' => ['2.5', '0', '2,5', true],
        'negative' => ['-4', '0.5', '-4.4', true],
        'no answer' => ['1', '0', null, false],
        'large values' => ['8849', '10', '8858', true],
    ]);

    it('validates answers', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['correct' => '1', 'tolerance' => '0']);

        expect(QuestionHandlers::answerErrors($definition, ['value' => '1.5']))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['value' => null]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['value' => '1e9']))->toHaveKey('value')
            ->and(QuestionHandlers::answerErrors($definition, ['value' => 1.5]))->toHaveKey('value')
            ->and(QuestionHandlers::answerErrors($definition, ['value' => str_repeat('9', 40)]))->toHaveKey('value');
    });

    it('shows only the unit to students', function () use ($type): void {
        /** @var NumericDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['correct' => '42.5', 'tolerance' => '0.25', 'unit' => 'kg']);
        $payload = QuestionHandlers::for($type)->toPlayerPayload($definition, Randomizers::secure(), true);

        expect($payload)->toBe(['unit' => 'kg'])
            ->and(PayloadLeakScanner::findings($payload, ['42.5', '0.25']))->toBe([]);
    });
});

describe('text', function (): void {
    $type = QuestionType::Text;

    it('validates the definition', function (array $input, ?string $field) use ($type): void {
        $errors = QuestionHandlers::definitionErrors($type, $input);

        $field === null ? expect($errors)->toBe([]) : expect($errors)->toHaveKey($field);
    })->with([
        'valid' => [['max_length' => 500, 'reference_answer' => 'Photosynthesis'], null],
        'no reference' => [['max_length' => 1], null],
        'max_length 0' => [['max_length' => 0], 'max_length'],
        'max_length 5001' => [['max_length' => 5001], 'max_length'],
        'not an integer' => [['max_length' => 'lots'], 'max_length'],
    ]);

    it('waits for a teacher unless the answer is empty', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['max_length' => 100, 'reference_answer' => 'Model answer']);
        $handler = QuestionHandlers::for($type);
        $points = Points::of('5');

        expect($handler->grade($definition, ['text' => 'My answer'], $points)->toArray())
            ->toBe(['is_correct' => null, 'awarded_points' => '0.00', 'needs_manual_grading' => true])
            ->and($handler->grade($definition, ['text' => '   '], $points)->toArray())
            ->toBe(['is_correct' => false, 'awarded_points' => '0.00', 'needs_manual_grading' => false])
            ->and($handler->grade($definition, null, $points)->needsManualGrading)->toBeFalse();
    });

    it('enforces the answer length', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['max_length' => 10]);

        expect(QuestionHandlers::answerErrors($definition, ['text' => str_repeat('a', 10)]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['text' => str_repeat('a', 11)]))->toHaveKey('text')
            ->and(QuestionHandlers::answerErrors($definition, ['text' => ['a']]))->toHaveKey('text');
    });

    it('never shows the reference answer to students, not even in the review', function () use ($type): void {
        /** @var TextDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['max_length' => 100, 'reference_answer' => 'Secret model answer']);
        $handler = QuestionHandlers::for($type);

        $player = $handler->toPlayerPayload($definition, Randomizers::secure(), true);
        $review = $handler->toReviewPayload($definition, ['text' => 'Mine'], true);

        expect($player)->toBe(['max_length' => 100])
            ->and(PayloadLeakScanner::findings($player, ['Secret model answer']))->toBe([])
            ->and(PayloadLeakScanner::findings($review, ['Secret model answer']))->toBe([]);
    });
});
