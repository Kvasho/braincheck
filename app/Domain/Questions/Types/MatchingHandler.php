<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\MatchingDefinition;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Domain\Shared\Randomizers;
use App\Enums\QuestionType;
use Illuminate\Validation\Rule;
use Random\Randomizer;

/**
 * matching: 2–10 pairs plus 0–5 distractors on the right side.
 * Partial credit per correct pair: (correct pairs / all pairs) × points.
 *
 * The right side is always shown shuffled (and stored shuffled): in stored
 * order it could line up with the left side and reveal the pairs.
 *
 * Editor input: `{ pairs: [{ left: {id?, text}, right: {id?, text} }], distractors: [{id?, text}] }`.
 * Answer: `{ pairs: [{ left: id, right: id }] }`, each side used at most once.
 * Review: the student's pairs are `given_pairs` (never `pairs`, the secret field).
 */
final class MatchingHandler extends BaseQuestionTypeHandler
{
    public const int MAX_DISTRACTORS = 5;

    public function type(): QuestionType
    {
        return QuestionType::Matching;
    }

    public function definitionRules(): array
    {
        $text = ['required', 'string', 'max:'.self::ITEM_TEXT_MAX, 'regex:/\S/u'];
        $id = ['nullable', 'string', 'max:8'];

        return [
            'pairs' => ['required', 'array', 'list', 'min:'.self::MIN_ITEMS, 'max:'.self::MAX_ITEMS],
            'pairs.*' => ['required', 'array:left,right'],
            'pairs.*.left' => ['required', 'array:id,text'],
            'pairs.*.left.id' => $id,
            'pairs.*.left.text' => $text,
            'pairs.*.right' => ['required', 'array:id,text'],
            'pairs.*.right.id' => $id,
            'pairs.*.right.text' => $text,
            ...$this->itemRowRules('distractors', 0, self::MAX_DISTRACTORS),
            'distractors' => ['present', 'array', 'list', 'max:'.self::MAX_DISTRACTORS],
        ];
    }

    public function normalizeDefinition(array $input): MatchingDefinition
    {
        $pairRows = is_array($input['pairs'] ?? null) ? array_values($input['pairs']) : [];
        $used = [];

        $left = $this->items(array_map(fn (mixed $pair): mixed => is_array($pair) ? ($pair['left'] ?? null) : null, $pairRows), $used);
        $pairedRight = $this->items(array_map(fn (mixed $pair): mixed => is_array($pair) ? ($pair['right'] ?? null) : null, $pairRows), $used);
        $distractors = $this->items(is_array($input['distractors'] ?? null) ? $input['distractors'] : [], $used);

        $pairs = array_map(
            fn (int $index): array => ['left' => $left[$index]->id, 'right' => $pairedRight[$index]->id],
            array_keys($left),
        );

        return new MatchingDefinition(
            $left,
            $this->shuffled([...$pairedRight, ...$distractors], Randomizers::secure()),
            $pairs,
        );
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, MatchingDefinition::class);

        $left = [];
        $right = [];

        foreach ($definition->left as $item) {
            $left[$item->id] = $item;
        }

        foreach ($definition->right as $item) {
            $right[$item->id] = $item;
        }

        $row = fn (Item $item): array => $withIds ? $item->toArray() : ['text' => $item->text];
        $paired = array_column($definition->pairs, 'right');

        return [
            'pairs' => array_map(fn (array $pair): array => [
                'left' => $row($left[$pair['left']]),
                'right' => $row($right[$pair['right']]),
            ], $definition->pairs),
            'distractors' => $this->editorRows(
                array_values(array_filter($definition->right, fn (Item $item): bool => ! in_array($item->id, $paired, true))),
                $withIds,
            ),
        ];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $definition = $this->expect($definition, MatchingDefinition::class);

        return [
            'pairs' => ['present', 'array', 'list', 'max:'.count($definition->left)],
            'pairs.*' => ['required', 'array:left,right'],
            'pairs.*.left' => ['required', 'string', 'distinct', Rule::in($this->ids($definition->left))],
            'pairs.*.right' => ['required', 'string', 'distinct', Rule::in($this->ids($definition->right))],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $definition = $this->expect($definition, MatchingDefinition::class);
        $given = $this->pairs($answer);
        $total = count($definition->pairs);

        $correct = 0;

        foreach ($definition->pairs as $pair) {
            if (($given[$pair['left']] ?? null) === $pair['right']) {
                $correct++;
            }
        }

        return $correct === $total
            ? GradeResult::full($points)
            : GradeResult::partial($points->timesFraction($correct, $total), $points);
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $definition = $this->expect($definition, MatchingDefinition::class);

        return [
            'left' => $this->itemArrays($this->shuffled($definition->left, $randomizer, $shuffle)),
            'right' => $this->itemArrays($this->shuffled($definition->right, $randomizer)),
        ];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $definition = $this->expect($definition, MatchingDefinition::class);
        $given = $this->pairs($answer);
        $correctFor = array_column($definition->pairs, 'right', 'left');

        return [
            'left' => $this->itemArrays($definition->left),
            'right' => $this->itemArrays($definition->right),
            'given_pairs' => array_map(fn (string $left, string $right): array => [
                'left' => $left,
                'right' => $right,
                ...($revealCorrect ? ['is_correct' => ($correctFor[$left] ?? null) === $right] : []),
            ], array_keys($given), array_values($given)),
            ...($revealCorrect ? ['correct_pairs' => $definition->pairs] : []),
        ];
    }

    /**
     * Answer pairs as left id => right id (first occurrence wins).
     *
     * @param  array<array-key, mixed>|null  $answer
     * @return array<string, string>
     */
    private function pairs(?array $answer): array
    {
        $pairs = $answer['pairs'] ?? [];
        $map = [];

        if (! is_array($pairs)) {
            return [];
        }

        foreach ($pairs as $pair) {
            if (is_array($pair) && is_string($pair['left'] ?? null) && is_string($pair['right'] ?? null) && ! isset($map[$pair['left']])) {
                $map[$pair['left']] = $pair['right'];
            }
        }

        return $map;
    }
}
