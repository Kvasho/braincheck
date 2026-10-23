<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Distractor analysis for choice questions: how many attempts picked each
 * option, overall and in the upper / lower 27 % groups (by total score).
 * Option IDs are stable across snapshots, so picks map back to options.
 *
 * Flags on wrong options ("distractors"):
 * - `never_picked`: nobody chose it (it distracts no one);
 * - `strong_pick`: the upper group chose it more often than the lower group
 *   (it misleads good students: check the wording or the key).
 */
final class DistractorAnalysis
{
    /**
     * @param  list<array{id: string, text: string, correct: bool}>  $options
     * @param  list<array{score: string, selected: list<string>}>  $responses
     * @return list<array{id: string, text: string, correct: bool, picks: int, upper: int, lower: int, flags: list<string>}>
     */
    public static function analyse(array $options, array $responses): array
    {
        $groups = count($responses) >= 2 ? ItemAnalysis::groups($responses) : ['upper' => [], 'lower' => []];
        $count = fn (array $group, string $id): int => count(array_filter($group, fn (array $response): bool => in_array($id, $response['selected'], true)));

        return array_map(function (array $option) use ($responses, $groups, $count): array {
            $picks = $count($responses, $option['id']);
            $upper = $count($groups['upper'], $option['id']);
            $lower = $count($groups['lower'], $option['id']);
            $flags = [];

            if (! $option['correct'] && $picks === 0 && $responses !== []) {
                $flags[] = 'never_picked';
            }

            if (! $option['correct'] && $upper > $lower) {
                $flags[] = 'strong_pick';
            }

            return [...$option, 'picks' => $picks, 'upper' => $upper, 'lower' => $lower, 'flags' => $flags];
        }, $options);
    }
}
