<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Enums\QuestionType;
use Random\Randomizer;

/**
 * true_false: the statement is the question body. Full points or 0.
 *
 * Editor input: `{ correct: bool }`. Answer: `{ value: bool | null }`.
 */
final class TrueFalseHandler extends BaseQuestionTypeHandler
{
    public function type(): QuestionType
    {
        return QuestionType::TrueFalse;
    }

    public function definitionRules(): array
    {
        return [
            'correct' => ['required', 'boolean'],
        ];
    }

    public function normalizeDefinition(array $input): TrueFalseDefinition
    {
        return new TrueFalseDefinition(filter_var($input['correct'] ?? false, FILTER_VALIDATE_BOOL));
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, TrueFalseDefinition::class);

        return ['correct' => $definition->correct];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $this->expect($definition, TrueFalseDefinition::class);

        return [
            'value' => ['present', 'nullable', 'boolean'],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $definition = $this->expect($definition, TrueFalseDefinition::class);
        $value = $this->value($answer);

        return $value !== null && $value === $definition->correct ? GradeResult::full($points) : GradeResult::zero();
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $this->expect($definition, TrueFalseDefinition::class);

        return [];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $definition = $this->expect($definition, TrueFalseDefinition::class);

        return [
            'value' => $this->value($answer),
            ...($revealCorrect ? ['correct' => $definition->correct] : []),
        ];
    }

    /**
     * @param  array<array-key, mixed>|null  $answer
     */
    private function value(?array $answer): ?bool
    {
        $value = $answer['value'] ?? null;

        return $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }
}
