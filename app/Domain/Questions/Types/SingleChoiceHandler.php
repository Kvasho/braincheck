<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Enums\QuestionType;
use Closure;
use Illuminate\Validation\Rule;
use Random\Randomizer;

/**
 * single_choice: 2–10 options, exactly one correct. Full points or 0.
 *
 * Editor input: `{ options: [{ id?, text, correct: bool }] }`.
 * Answer: `{ selected: id | null }`.
 */
final class SingleChoiceHandler extends BaseQuestionTypeHandler
{
    public function type(): QuestionType
    {
        return QuestionType::SingleChoice;
    }

    public function definitionRules(): array
    {
        return [
            ...$this->itemRowRules('options', self::MIN_ITEMS, self::MAX_ITEMS, ['correct' => ['required', 'boolean']]),
            'options' => [
                'required', 'array', 'list', 'min:'.self::MIN_ITEMS, 'max:'.self::MAX_ITEMS,
                function (string $attribute, mixed $value, Closure $fail): void {
                    $correct = is_array($value) ? count(array_filter($value, fn (mixed $row): bool => is_array($row) && filter_var($row['correct'] ?? false, FILTER_VALIDATE_BOOL))) : 0;

                    if ($correct !== 1) {
                        $fail(__('Mark exactly one option as correct.'));
                    }
                },
            ],
        ];
    }

    public function normalizeDefinition(array $input): SingleChoiceDefinition
    {
        $rows = is_array($input['options'] ?? null) ? array_values($input['options']) : [];
        $used = [];
        $options = $this->items($rows, $used);

        $correctIndex = array_key_first(array_filter($rows, fn (mixed $row): bool => is_array($row) && filter_var($row['correct'] ?? false, FILTER_VALIDATE_BOOL)));

        // No flagged option: the value object rejects the empty id (InvalidQuestionDefinition).
        return new SingleChoiceDefinition($options, $correctIndex === null ? '' : $options[$correctIndex]->id);
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, SingleChoiceDefinition::class);

        return [
            'options' => array_map(
                fn (array $row, Item $option): array => [...$row, 'correct' => $option->id === $definition->correct],
                $this->editorRows($definition->options, $withIds),
                $definition->options,
            ),
        ];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $definition = $this->expect($definition, SingleChoiceDefinition::class);

        return [
            'selected' => ['present', 'nullable', 'string', Rule::in($this->ids($definition->options))],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $definition = $this->expect($definition, SingleChoiceDefinition::class);

        return ($answer['selected'] ?? null) === $definition->correct ? GradeResult::full($points) : GradeResult::zero();
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $definition = $this->expect($definition, SingleChoiceDefinition::class);

        return ['options' => $this->itemArrays($this->shuffled($definition->options, $randomizer, $shuffle))];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $definition = $this->expect($definition, SingleChoiceDefinition::class);
        $selected = is_string($answer['selected'] ?? null) ? $answer['selected'] : null;

        return [
            'options' => array_map(fn (Item $option): array => [
                ...$option->toArray(),
                'selected' => $option->id === $selected,
                ...($revealCorrect ? ['is_correct' => $option->id === $definition->correct] : []),
            ], $definition->options),
            'selected' => $selected,
            ...($revealCorrect ? ['correct' => $definition->correct] : []),
        ];
    }
}
