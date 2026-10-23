<?php

declare(strict_types=1);

use App\Domain\Analytics\DistractorAnalysis;
use App\Domain\Analytics\ItemAnalysis;
use App\Domain\Analytics\Statistics;

/*
 * Every expected value below is calculated by hand.
 */
describe('descriptive statistics', function (): void {
    it('computes mean, median and population σ of an odd dataset', function (): void {
        // 60 70 80 90 100: mean 80; median 80; σ = √((400+100+0+100+400)/5) = √200 = 14.142… → 14.14
        $values = ['60', '70', '80', '90', '100'];

        expect(Statistics::mean($values))->toBe('80.00')
            ->and(Statistics::median($values))->toBe('80.00')
            ->and(Statistics::standardDeviation($values))->toBe('14.14');
    });

    it('takes the mean of the two middle values for an even dataset', function (): void {
        // 40 10 30 20 (unsorted): median (20+30)/2 = 25; mean 25; σ = √((225+25+25+225)/4) = √125 = 11.180… → 11.18
        $values = ['40', '10', '30', '20'];

        expect(Statistics::median($values))->toBe('25.00')
            ->and(Statistics::mean($values))->toBe('25.00')
            ->and(Statistics::standardDeviation($values))->toBe('11.18');
    });

    it('rounds half up and handles empty input and a single value', function (): void {
        expect(Statistics::mean(['33.33', '33.34', '33.34']))->toBe('33.34') // 100.01 / 3 = 33.336…
            ->and(Statistics::mean([]))->toBeNull()
            ->and(Statistics::median([]))->toBeNull()
            ->and(Statistics::standardDeviation(['50']))->toBe('0.00');
    });

    it('buckets percents in 10 % steps, 100 in the last bucket', function (): void {
        $counts = array_column(Statistics::histogram(['0', '9.99', '10', '55', '100', '99.99']), 'count');

        expect($counts)->toBe([2, 1, 0, 0, 0, 1, 0, 0, 0, 2]);
    });
});

describe('item analysis', function (): void {
    /**
     * Ten attempts with total scores 100…10; $shares[i] belongs to the i-th score.
     *
     * @param  list<string>  $shares
     * @return list<array{score: string, share: string}>
     */
    $responses = fn (array $shares): array => array_map(
        fn (string $share, int $index): array => ['score' => (string) (100 - $index * 10), 'share' => $share],
        $shares,
        array_keys($shares),
    );

    it('uses groups of 27 % (rounded, at least one)', function (): void {
        expect(ItemAnalysis::groupSize(10))->toBe(3)  // 2.7 → 3
            ->and(ItemAnalysis::groupSize(100))->toBe(27)
            ->and(ItemAnalysis::groupSize(2))->toBe(1)  // 0.54 → 1
            ->and(ItemAnalysis::groupSize(1))->toBe(1);
    });

    it('computes difficulty and discrimination', function () use ($responses): void {
        // Shares by score 100…10: 1 1 0 1 0 1 0 0 0 1 → p = 5/10 = 0.5.
        // Upper (100, 90, 80): 1 1 0 → 0.6667; lower (30, 20, 10): 0 0 1 → 0.3333; D = 0.3333.
        $result = ItemAnalysis::analyse($responses(['1', '1', '0', '1', '0', '1', '0', '0', '0', '1']));

        expect($result)->toBe(['difficulty' => '0.5000', 'discrimination' => '0.3333', 'needs_review' => false]);
    });

    it('flags questions that weak students answer better (D < 0.2)', function () use ($responses): void {
        // Upper: 0 0 0 → 0; lower: 1 1 1 → 1; D = −1. p = 4/10.
        $result = ItemAnalysis::analyse($responses(['0', '0', '0', '1', '0', '0', '0', '1', '1', '1']));

        expect($result)->toBe(['difficulty' => '0.4000', 'discrimination' => '-1.0000', 'needs_review' => true]);
    });

    it('averages partial credit and needs two attempts for D', function () use ($responses): void {
        expect(ItemAnalysis::analyse($responses(['0.5', '0.25']))['difficulty'])->toBe('0.3750')
            ->and(ItemAnalysis::analyse($responses(['1'])))->toBe(['difficulty' => '1.0000', 'discrimination' => null, 'needs_review' => false])
            ->and(ItemAnalysis::analyse([]))->toBe(['difficulty' => null, 'discrimination' => null, 'needs_review' => false]);
    });
});

describe('distractor analysis', function (): void {
    it('counts picks and flags unused and misleading distractors', function (): void {
        $options = [
            ['id' => 'A', 'text' => 'Right', 'correct' => true],
            ['id' => 'B', 'text' => 'Tempting', 'correct' => false],
            ['id' => 'C', 'text' => 'Plain wrong', 'correct' => false],
            ['id' => 'D', 'text' => 'Nobody', 'correct' => false],
        ];
        // Scores 100…10. Upper (100, 90, 80): A A B; lower (30, 20, 10): C C A.
        $picks = ['A', 'A', 'B', 'A', 'C', 'A', 'C', 'C', 'C', 'A'];
        $responses = array_map(fn (string $id, int $i): array => ['score' => (string) (100 - $i * 10), 'selected' => [$id]], $picks, array_keys($picks));

        $result = collect(DistractorAnalysis::analyse($options, $responses))->keyBy('id');

        expect($result['A'])->toMatchArray(['picks' => 5, 'upper' => 2, 'lower' => 1, 'flags' => []])
            ->and($result['B'])->toMatchArray(['picks' => 1, 'upper' => 1, 'lower' => 0, 'flags' => ['strong_pick']])
            ->and($result['C'])->toMatchArray(['picks' => 4, 'upper' => 0, 'lower' => 2, 'flags' => []])
            ->and($result['D'])->toMatchArray(['picks' => 0, 'flags' => ['never_picked']]);
    });
});
