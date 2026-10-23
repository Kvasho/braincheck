<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\MultipleChoiceDefinition;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use Closure;
use Illuminate\Validation\Rule;
use Random\Randomizer;

/**
 * multiple_choice: 2–10 options, at least one correct.
 *
 * - all_or_nothing: full points only for exactly the correct set.
 * - partial: max(0, (correctSelected − wrongSelected) / totalCorrect) × points,
 *   rounded half up to 2 decimals.
 *
 * Editor input: `{ options: [{ id?, text, correct: bool }], scoring }`.
 * Answer: `{ selected: [ids] }` (distinct, existing ids).
 */
final class MultipleChoiceHandler extends BaseQuestionTypeHandler
{
    public function type(): QuestionType
    {
        return QuestionType::MultipleChoice;
    }

    public function definitionRules(): array
    {
        return [
            ...$this->itemRowRules('options', self::MIN_ITEMS, self::MAX_ITEMS, ['correct' => ['required', 'boolean']]),
            'options' => [
                'required', 'array', 'list', 'min:'.self::MIN_ITEMS, 'max:'.self::MAX_ITEMS,
                function (string $attribute, mixed $value, Closure $fail): void {
                    $correct = is_array($value) ? count(array_filter($value, fn (mixed $row): bool => is_array($row) && filter_var($row['correct'] ?? false, FILTER_VALIDATE_BOOL))) : 0;

                    if ($correct < 1) {
                        $fail(__('Mark at least one option as correct.'));
                    }
                },
            ],
            'scoring' => ['required', Rule::enum(MultipleChoiceScoring::class)],
        ];
    }

    public function normalizeDefinition(array $input): MultipleChoiceDefinition
    {
        $rows = is_array($input['options'] ?? null) ? array_values($input['options']) : [];
        $used = [];
        $options = $this->items($rows, $used);

        $correct = [];

        foreach ($rows as $index => $row) {
            if (is_array($row) && filter_var($row['correct'] ?? false, FILTER_VALIDATE_BOOL)) {
                $correct[] = $options[$index]->id;
            }
        }

        $scoring = MultipleChoiceScoring::tryFrom(is_string($input['scoring'] ?? null) ? $input['scoring'] : '') ?? MultipleChoiceScoring::AllOrNothing;

        return new MultipleChoiceDefinition($options, $correct, $scoring);
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, MultipleChoiceDefinition::class);

        return [
            'options' => array_map(
                fn (array $row, Item $option): array => [...$row, 'correct' => in_array($option->id, $definition->correct, true)],
                $this->editorRows($definition->options, $withIds),
                $definition->options,
            ),
            'scoring' => $definition->scoring->value,
        ];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $definition = $this->expect($definition, MultipleChoiceDefinition::class);

        return [
            'selected' => ['present', 'array', 'list', 'max:'.count($definition->options)],
            'selected.*' => ['required', 'string', 'distinct', Rule::in($this->ids($definition->options))],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $definition = $this->expect($definition, MultipleChoiceDefinition::class);
        $selected = $this->selected($answer);

        $correctSelected = count(array_intersect($selected, $definition->correct));
        $wrongSelected = count($selected) - $correctSelected;
        $totalCorrect = count($definition->correct);

        if ($correctSelected === $totalCorrect && $wrongSelected === 0) {
            return GradeResult::full($points);
        }

        if ($definition->scoring === MultipleChoiceScoring::AllOrNothing) {
            return GradeResult::zero();
        }

        return GradeResult::partial($points->timesFraction($correctSelected - $wrongSelected, $totalCorrect), $points);
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $definition = $this->expect($definition, MultipleChoiceDefinition::class);

        // No count of correct options: "choose N" would reveal part of the answer.
        return ['options' => $this->itemArrays($this->shuffled($definition->options, $randomizer, $shuffle))];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $definition = $this->expect($definition, MultipleChoiceDefinition::class);
        $selected = $this->selected($answer);

        return [
            'options' => array_map(fn (Item $option): array => [
                ...$option->toArray(),
                'selected' => in_array($option->id, $selected, true),
                ...($revealCorrect ? ['is_correct' => in_array($option->id, $definition->correct, true)] : []),
            ], $definition->options),
            'selected' => $selected,
            ...($revealCorrect ? ['correct' => $definition->correct, 'scoring' => $definition->scoring->value] : []),
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $answer
     * @return list<string>
     */
    private function selected(?array $answer): array
    {
        $selected = $answer['selected'] ?? [];

        return is_array($selected) ? array_values(array_unique(array_filter($selected, 'is_string'))) : [];
    }
}
