<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Enums\QuestionType;
use Random\Randomizer;

/**
 * text: free answer, graded by a teacher (needsManualGrading, 0 points until
 * graded). An empty answer is graded 0 right away (nothing to grade).
 * The reference answer is for the teacher only: never in player or review payloads.
 *
 * Editor input: `{ max_length: 1–5000, reference_answer?: string }`.
 * Answer: `{ text: string | null }` (at most max_length characters).
 */
final class TextHandler extends BaseQuestionTypeHandler
{
    public const int MAX_LENGTH = 5000;

    public function type(): QuestionType
    {
        return QuestionType::Text;
    }

    public function definitionRules(): array
    {
        return [
            'max_length' => ['required', 'integer', 'min:1', 'max:'.self::MAX_LENGTH],
            'reference_answer' => ['nullable', 'string', 'max:'.self::MAX_LENGTH],
        ];
    }

    public function normalizeDefinition(array $input): TextDefinition
    {
        $reference = is_string($input['reference_answer'] ?? null) ? trim($input['reference_answer']) : '';

        return new TextDefinition(
            (int) filter_var($input['max_length'] ?? 0, FILTER_VALIDATE_INT),
            $reference === '' ? null : $reference,
        );
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, TextDefinition::class);

        return ['max_length' => $definition->maxLength, 'reference_answer' => $definition->referenceAnswer];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $definition = $this->expect($definition, TextDefinition::class);

        return [
            'text' => ['present', 'nullable', 'string', 'max:'.$definition->maxLength],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $this->expect($definition, TextDefinition::class);

        return $this->text($answer) === '' ? GradeResult::zero() : GradeResult::pendingManualGrading();
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $definition = $this->expect($definition, TextDefinition::class);

        return ['max_length' => $definition->maxLength];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $this->expect($definition, TextDefinition::class);

        return ['text' => $this->text($answer)];
    }

    /**
     * @param  array<array-key, mixed>|null  $answer
     */
    private function text(?array $answer): string
    {
        return is_string($answer['text'] ?? null) ? trim($answer['text']) : '';
    }
}
