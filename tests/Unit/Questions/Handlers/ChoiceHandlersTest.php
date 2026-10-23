<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\MultipleChoiceDefinition;
use App\Domain\Questions\Definitions\ShortId;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use Tests\Support\PayloadLeakScanner;
use Tests\Support\QuestionHandlers;

/**
 * @param  list<bool>  $correct
 * @return list<array{text: string, correct: bool}>
 */
function optionRows(array $correct): array
{
    return array_map(fn (bool $isCorrect, int $i): array => ['text' => 'Option '.($i + 1), 'correct' => $isCorrect], $correct, array_keys($correct));
}

describe('single_choice', function (): void {
    $type = QuestionType::SingleChoice;

    it('accepts a valid definition', function () use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, ['options' => optionRows([true, false, false])]))->toBe([]);
    });

    it('rejects every invalid definition', function (array $input, string $field) use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, $input))->toHaveKey($field);
    })->with([
        'no options' => [[], 'options'],
        'one option' => [['options' => optionRows([true])], 'options'],
        'eleven options' => [['options' => optionRows([true, ...array_fill(0, 10, false)])], 'options'],
        'no correct option' => [['options' => optionRows([false, false])], 'options'],
        'two correct options' => [['options' => optionRows([true, true, false])], 'options'],
        'blank text' => [['options' => [['text' => '   ', 'correct' => true], ['text' => 'B', 'correct' => false]]], 'options.0.text'],
        'text too long' => [['options' => [['text' => str_repeat('a', 501), 'correct' => true], ['text' => 'B', 'correct' => false]]], 'options.0.text'],
        'unknown row key' => [['options' => [['text' => 'A', 'correct' => true, 'hint' => 'x'], ['text' => 'B', 'correct' => false]]], 'options.0'],
        'correct not a boolean' => [['options' => [['text' => 'A', 'correct' => 'yes'], ['text' => 'B', 'correct' => false]]], 'options.0.correct'],
        'not a list' => [['options' => ['a' => ['text' => 'A', 'correct' => true], 'b' => ['text' => 'B', 'correct' => false]]], 'options'],
    ]);

    it('normalizes: trims text, generates unique random ids, keeps valid existing ids', function () use ($type): void {
        /** @var SingleChoiceDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['options' => [
            ['id' => 'ABCDEFGH', 'text' => '  Tbilisi  ', 'correct' => true],
            ['id' => 'ABCDEFGH', 'text' => 'Batumi', 'correct' => false], // duplicate id → fresh
            ['id' => '1', 'text' => 'Kutaisi', 'correct' => false],        // invalid id → fresh
        ]]);

        $ids = array_map(fn ($option) => $option->id, $definition->options);

        expect($definition->options[0]->text)->toBe('Tbilisi')
            ->and($ids[0])->toBe('ABCDEFGH')
            ->and(array_unique($ids))->toHaveCount(3)
            ->and(array_filter($ids, fn (string $id): bool => ! ShortId::isValid($id)))->toBe([])
            ->and($definition->correct)->toBe('ABCDEFGH');
    });

    it('grades full or zero, including empty and null answers', function () use ($type): void {
        /** @var SingleChoiceDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['options' => optionRows([false, true, false])]);
        $handler = QuestionHandlers::for($type);
        $points = Points::of('2');
        $wrong = $definition->options[0]->id;

        expect($handler->grade($definition, ['selected' => $definition->correct], $points)->toArray())->toBe(['is_correct' => true, 'awarded_points' => '2.00', 'needs_manual_grading' => false])
            ->and($handler->grade($definition, ['selected' => $wrong], $points)->awardedPoints->toString())->toBe('0.00')
            ->and($handler->grade($definition, ['selected' => null], $points)->isCorrect)->toBeFalse()
            ->and($handler->grade($definition, null, $points)->awardedPoints->isZero())->toBeTrue();
    });

    it('validates answers against this definition', function () use ($type): void {
        /** @var SingleChoiceDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['options' => optionRows([true, false])]);

        expect(QuestionHandlers::answerErrors($definition, ['selected' => $definition->correct]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => null]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => 'ZZZZZZZZ']))->toHaveKey('selected')
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => [$definition->correct]]))->toHaveKey('selected')
            ->and(QuestionHandlers::answerErrors($definition, []))->toHaveKey('selected');
    });
});

describe('multiple_choice', function (): void {
    $type = QuestionType::MultipleChoice;

    $make = fn (array $correct, MultipleChoiceScoring $scoring): MultipleChoiceDefinition => QuestionHandlers::normalize($type, [
        'options' => optionRows($correct),
        'scoring' => $scoring->value,
    ]);

    it('rejects every invalid definition', function (array $input, string $field) use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, $input))->toHaveKey($field);
    })->with([
        'no correct option' => [['options' => optionRows([false, false]), 'scoring' => 'partial'], 'options'],
        'missing scoring' => [['options' => optionRows([true, false])], 'scoring'],
        'unknown scoring' => [['options' => optionRows([true, false]), 'scoring' => 'generous'], 'scoring'],
        'one option' => [['options' => optionRows([true]), 'scoring' => 'partial'], 'options'],
        'eleven options' => [['options' => optionRows(array_fill(0, 11, true)), 'scoring' => 'partial'], 'options'],
    ]);

    it('accepts several correct options', function () use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, ['options' => optionRows([true, true, false]), 'scoring' => 'partial']))->toBe([]);
    });

    it('grades all or nothing', function () use ($make): void {
        $definition = $make([true, true, false, false], MultipleChoiceScoring::AllOrNothing);
        [$a, $b, $c] = array_map(fn ($o) => $o->id, $definition->options);
        $handler = QuestionHandlers::for(QuestionType::MultipleChoice);
        $points = Points::of('3');

        expect($handler->grade($definition, ['selected' => [$b, $a]], $points)->awardedPoints->toString())->toBe('3.00')
            ->and($handler->grade($definition, ['selected' => [$a]], $points)->awardedPoints->toString())->toBe('0.00')
            ->and($handler->grade($definition, ['selected' => [$a, $b, $c]], $points)->awardedPoints->toString())->toBe('0.00')
            ->and($handler->grade($definition, ['selected' => []], $points)->isCorrect)->toBeFalse();
    });

    it('grades partial credit: max(0, (correct − wrong) / total correct) × points, half up', function (array $pick, string $expected) use ($make): void {
        // Options 0, 1, 2 are correct; 3 and 4 are wrong. 1 point.
        $definition = $make([true, true, true, false, false], MultipleChoiceScoring::Partial);
        $ids = array_map(fn ($o) => $o->id, $definition->options);
        $selected = array_map(fn (int $i): string => $ids[$i], $pick);

        $result = QuestionHandlers::for(QuestionType::MultipleChoice)->grade($definition, ['selected' => $selected], Points::of('1'));

        expect($result->awardedPoints->toString())->toBe($expected)
            ->and($result->isCorrect)->toBe($expected === '1.00');
    })->with([
        'all correct' => [[0, 1, 2], '1.00'],
        'two of three' => [[0, 1], '0.67'],
        'one of three' => [[0], '0.33'],
        'two correct, one wrong' => [[0, 1, 3], '0.33'],
        'one correct, one wrong' => [[0, 3], '0.00'],
        'one correct, two wrong (never negative)' => [[0, 3, 4], '0.00'],
        'everything selected' => [[0, 1, 2, 3, 4], '0.33'],
        'nothing' => [[], '0.00'],
    ]);

    it('validates answers: known, distinct ids, bounded size', function () use ($make): void {
        $definition = $make([true, false, false], MultipleChoiceScoring::Partial);
        [$a, $b] = array_map(fn ($o) => $o->id, $definition->options);

        expect(QuestionHandlers::answerErrors($definition, ['selected' => [$a, $b]]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => []]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => [$a, $a]]))->toHaveKey('selected.0')
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => ['ZZZZZZZZ']]))->toHaveKey('selected.0')
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => $a]))->toHaveKey('selected')
            ->and(QuestionHandlers::answerErrors($definition, ['selected' => [$a, $b, $a, $b]]))->toHaveKey('selected');
    });
});

describe('player payload', function (): void {
    it('never leaks the answer', function (QuestionType $type, array $input): void {
        $definition = QuestionHandlers::normalize($type, $input);
        $payload = QuestionHandlers::for($type)->toPlayerPayload($definition, Randomizers::secure(), true);

        expect(PayloadLeakScanner::findings($payload))->toBe([])
            ->and(array_keys($payload))->toBe(['options'])
            ->and(array_keys($payload['options'][0]))->toBe(['id', 'text']);
    })->with([
        'single' => [QuestionType::SingleChoice, ['options' => optionRows([false, true, false])]],
        'multiple' => [QuestionType::MultipleChoice, ['options' => optionRows([true, true, false]), 'scoring' => 'partial']],
    ]);

    it('shuffles per seed while ids stay stable, and keeps order without shuffle', function (QuestionType $type, array $input): void {
        $definition = QuestionHandlers::normalize($type, $input);
        $handler = QuestionHandlers::for($type);
        $order = fn (string $seed, bool $shuffle = true): array => array_column($handler->toPlayerPayload($definition, Randomizers::seeded($seed), $shuffle)['options'], 'id');

        $stored = array_map(fn ($o) => $o->id, $definition->options);
        $orders = array_map(fn (int $seed): string => implode(',', $order("seed-{$seed}")), range(1, 20));

        expect(count(array_unique($orders)))->toBeGreaterThan(1)
            ->and($order('seed-1'))->toEqualCanonicalizing($stored)
            ->and($order('seed-1'))->toBe($order('seed-1'))
            ->and($order('seed-1', false))->toBe($stored);
    })->with([
        'single' => [QuestionType::SingleChoice, ['options' => optionRows([true, false, false, false, false, false])]],
        'multiple' => [QuestionType::MultipleChoice, ['options' => optionRows([true, true, false, false, false, false]), 'scoring' => 'partial']],
    ]);
});

describe('review payload', function (): void {
    it('reveals the correct answer only when asked', function (): void {
        /** @var SingleChoiceDefinition $definition */
        $definition = QuestionHandlers::normalize(QuestionType::SingleChoice, ['options' => optionRows([true, false])]);
        $handler = QuestionHandlers::for(QuestionType::SingleChoice);
        $answer = ['selected' => $definition->options[1]->id];

        $hidden = $handler->toReviewPayload($definition, $answer, false);
        $revealed = $handler->toReviewPayload($definition, $answer, true);

        expect(PayloadLeakScanner::findings($hidden))->toBe([])
            ->and($hidden['selected'])->toBe($definition->options[1]->id)
            ->and($revealed['correct'])->toBe($definition->correct)
            ->and($revealed['options'][0]['is_correct'])->toBeTrue()
            ->and($revealed['options'][1]['selected'])->toBeTrue();
    });
});
