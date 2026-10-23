<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\MatchingDefinition;
use App\Domain\Questions\Definitions\OrderingDefinition;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Enums\QuestionType;
use Tests\Support\QuestionHandlers;

/**
 * @return list<array{text: string}>
 */
function textRows(int $count, string $prefix = 'Item'): array
{
    return array_map(fn (int $i): array => ['text' => "{$prefix} {$i}"], range(1, $count));
}

/**
 * @return array{pairs: list<array{left: array{text: string}, right: array{text: string}}>, distractors: list<array{text: string}>}
 */
function matchingInput(int $pairs, int $distractors = 0): array
{
    return [
        'pairs' => array_map(fn (int $i): array => ['left' => ['text' => "Country {$i}"], 'right' => ['text' => "Capital {$i}"]], range(1, $pairs)),
        'distractors' => textRows($distractors, 'Distractor'),
    ];
}

describe('ordering', function (): void {
    $type = QuestionType::Ordering;

    it('rejects every invalid definition', function (array $input, string $field) use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, $input))->toHaveKey($field);
    })->with([
        'one item' => [['items' => textRows(1), 'scoring' => 'partial'], 'items'],
        'eleven items' => [['items' => textRows(11), 'scoring' => 'partial'], 'items'],
        'blank item' => [['items' => [['text' => ' '], ['text' => 'B']], 'scoring' => 'partial'], 'items.0.text'],
        'missing scoring' => [['items' => textRows(3)], 'scoring'],
    ]);

    it('stores the correct order from the editor and the items shuffled', function () use ($type): void {
        /** @var OrderingDefinition $definition */
        $definition = QuestionHandlers::normalize($type, ['items' => textRows(3), 'scoring' => 'partial']);
        $textById = array_column(array_map(fn ($item) => $item->toArray(), $definition->items), 'text', 'id');

        expect(array_map(fn (string $id): string => $textById[$id], $definition->correctOrder))->toBe(['Item 1', 'Item 2', 'Item 3'])
            ->and(array_unique(array_keys($textById)))->toHaveCount(3);
    });

    it('grades all or nothing and partial (share in the right position)', function (array $order, string $allOrNothing, string $partial) use ($type): void {
        $make = fn (string $scoring): OrderingDefinition => QuestionHandlers::normalize($type, ['items' => textRows(4), 'scoring' => $scoring]);

        foreach (['all_or_nothing' => $allOrNothing, 'partial' => $partial] as $scoring => $expected) {
            $definition = $make($scoring);
            $answer = ['order' => array_map(fn (int $i): string => $definition->correctOrder[$i], $order)];

            expect(QuestionHandlers::for($type)->grade($definition, $answer, Points::of('2'))->awardedPoints->toString())->toBe($expected);
        }
    })->with([
        'correct' => [[0, 1, 2, 3], '2.00', '2.00'],
        'two swapped' => [[1, 0, 2, 3], '0.00', '1.00'],
        'one in place' => [[0, 2, 3, 1], '0.00', '0.50'],
        'reversed' => [[3, 2, 1, 0], '0.00', '0.00'],
    ]);

    it('rounds partial credit half up', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['items' => textRows(3), 'scoring' => 'partial']);
        [$a, $b, $c] = $definition->correctOrder;

        // 1 of 3 in place × 1 point = 0.333… → 0.33
        expect(QuestionHandlers::for($type)->grade($definition, ['order' => [$a, $c, $b]], Points::of('1'))->awardedPoints->toString())->toBe('0.33')
            ->and(QuestionHandlers::for($type)->grade($definition, null, Points::of('1'))->awardedPoints->toString())->toBe('0.00');
    });

    it('validates answers: a full permutation of known ids', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['items' => textRows(3), 'scoring' => 'partial']);
        [$a, $b, $c] = $definition->correctOrder;

        expect(QuestionHandlers::answerErrors($definition, ['order' => [$c, $b, $a]]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['order' => [$a, $b]]))->toHaveKey('order')
            ->and(QuestionHandlers::answerErrors($definition, ['order' => [$a, $a, $b]]))->toHaveKey('order.0')
            ->and(QuestionHandlers::answerErrors($definition, ['order' => [$a, $b, 'ZZZZZZZZ']]))->toHaveKey('order.2')
            ->and(QuestionHandlers::answerErrors($definition, ['order' => "{$a},{$b},{$c}"]))->toHaveKey('order');
    });

    it('always shuffles the player items, even with shuffling off', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, ['items' => textRows(6), 'scoring' => 'partial']);
        $handler = QuestionHandlers::for($type);
        $orders = array_map(
            fn (int $seed): string => implode(',', array_column($handler->toPlayerPayload($definition, Randomizers::seeded("s{$seed}"), false)['items'], 'id')),
            range(1, 20),
        );

        expect(count(array_unique($orders)))->toBeGreaterThan(1);
    });
});

describe('matching', function (): void {
    $type = QuestionType::Matching;

    it('rejects every invalid definition', function (array $input, string $field) use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, $input))->toHaveKey($field);
    })->with([
        'one pair' => [matchingInput(1), 'pairs'],
        'eleven pairs' => [matchingInput(11), 'pairs'],
        'six distractors' => [matchingInput(2, 6), 'distractors'],
        'pair without right' => [['pairs' => [['left' => ['text' => 'A']], ['left' => ['text' => 'B'], 'right' => ['text' => 'C']]], 'distractors' => []], 'pairs.0.right'],
        'blank text' => [['pairs' => [['left' => ['text' => ' '], 'right' => ['text' => 'x']], ['left' => ['text' => 'B'], 'right' => ['text' => 'C']]], 'distractors' => []], 'pairs.0.left.text'],
        'missing distractors' => [['pairs' => matchingInput(2)['pairs']], 'distractors'],
    ]);

    it('accepts 2–10 pairs and up to 5 distractors', function () use ($type): void {
        expect(QuestionHandlers::definitionErrors($type, matchingInput(2)))->toBe([])
            ->and(QuestionHandlers::definitionErrors($type, matchingInput(10, 5)))->toBe([]);
    });

    it('normalizes: unique ids across both sides, pairs kept, right side shuffled with distractors', function () use ($type): void {
        /** @var MatchingDefinition $definition */
        $definition = QuestionHandlers::normalize($type, matchingInput(3, 2));
        $ids = [...array_map(fn ($i) => $i->id, $definition->left), ...array_map(fn ($i) => $i->id, $definition->right)];
        $leftText = array_column(array_map(fn ($i) => $i->toArray(), $definition->left), 'text', 'id');
        $rightText = array_column(array_map(fn ($i) => $i->toArray(), $definition->right), 'text', 'id');

        expect(array_unique($ids))->toHaveCount(8)
            ->and($definition->right)->toHaveCount(5)
            ->and(array_map(fn (array $pair): array => [$leftText[$pair['left']], $rightText[$pair['right']]], $definition->pairs))
            ->toBe([['Country 1', 'Capital 1'], ['Country 2', 'Capital 2'], ['Country 3', 'Capital 3']]);
    });

    it('gives partial credit per correct pair', function (int $correctPairs, string $expected) use ($type): void {
        /** @var MatchingDefinition $definition */
        $definition = QuestionHandlers::normalize($type, matchingInput(3, 1));
        $distractor = collect($definition->right)->first(fn ($item) => ! in_array($item->id, array_column($definition->pairs, 'right'), true))->id;

        $answer = ['pairs' => array_map(
            fn (array $pair, int $i): array => ['left' => $pair['left'], 'right' => $i < $correctPairs ? $pair['right'] : $distractor],
            $definition->pairs,
            array_keys($definition->pairs),
        )];
        // Only one wrong pair may use the distractor (each right id once): drop the extra wrong ones.
        $answer['pairs'] = array_slice($answer['pairs'], 0, min(3, $correctPairs + 1));

        $result = QuestionHandlers::for($type)->grade($definition, $answer, Points::of('1'));

        expect($result->awardedPoints->toString())->toBe($expected)
            ->and($result->isCorrect)->toBe($expected === '1.00');
    })->with([
        'all three' => [3, '1.00'],
        'two of three' => [2, '0.67'],
        'one of three' => [1, '0.33'],
        'none' => [0, '0.00'],
    ]);

    it('validates answers: known ids, each side at most once', function () use ($type): void {
        /** @var MatchingDefinition $definition */
        $definition = QuestionHandlers::normalize($type, matchingInput(2, 1));
        [$p1, $p2] = $definition->pairs;

        expect(QuestionHandlers::answerErrors($definition, ['pairs' => [$p1, $p2]]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['pairs' => [$p1]]))->toBe([])
            ->and(QuestionHandlers::answerErrors($definition, ['pairs' => [$p1, ['left' => $p1['left'], 'right' => $p2['right']]]]))->toHaveKey('pairs.0.left')
            ->and(QuestionHandlers::answerErrors($definition, ['pairs' => [$p1, ['left' => $p2['left'], 'right' => $p1['right']]]]))->toHaveKey('pairs.0.right')
            ->and(QuestionHandlers::answerErrors($definition, ['pairs' => [['left' => $p1['right'], 'right' => $p1['left']]]]))->toHaveKeys(['pairs.0.left', 'pairs.0.right'])
            ->and(QuestionHandlers::answerErrors($definition, ['pairs' => [[...$p1, 'extra' => 1]]]))->toHaveKey('pairs.0')
            ->and(QuestionHandlers::answerErrors($definition, ['pairs' => [$p1, $p2, $p1]]))->toHaveKey('pairs');
    });

    it('always shuffles the right side', function () use ($type): void {
        $definition = QuestionHandlers::normalize($type, matchingInput(5));
        $handler = QuestionHandlers::for($type);
        $orders = array_map(
            fn (int $seed): string => implode(',', array_column($handler->toPlayerPayload($definition, Randomizers::seeded("s{$seed}"), false)['right'], 'id')),
            range(1, 20),
        );

        expect(count(array_unique($orders)))->toBeGreaterThan(1);
    });
});
