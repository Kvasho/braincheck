<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\NumericDefinition;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Enums\QuestionType;
use Brick\Math\BigDecimal;
use Random\Randomizer;

/**
 * numeric: correct when |value − correct| ≤ tolerance, compared with brick/math
 * (never floats). The unit is display only. Full points or 0.
 *
 * Editor input: `{ correct: "9.81", tolerance: "0.05", unit?: string }`.
 * Answer: `{ value: "9.8" | null }`; a decimal comma ("9,8") is accepted.
 */
final class NumericHandler extends BaseQuestionTypeHandler
{
    /** Plain decimals only: no exponent, no thousands separator. */
    public const string DECIMAL_PATTERN = '/^-?\d{1,20}([.,]\d{1,10})?$/';

    public function type(): QuestionType
    {
        return QuestionType::Numeric;
    }

    public function definitionRules(): array
    {
        return [
            'correct' => ['required', 'string', 'max:'.NumericDefinition::MAX_LENGTH, 'regex:'.self::DECIMAL_PATTERN],
            'tolerance' => ['required', 'string', 'max:'.NumericDefinition::MAX_LENGTH, 'regex:/^\d{1,20}([.,]\d{1,10})?$/'],
            'unit' => ['nullable', 'string', 'max:32'],
        ];
    }

    public function normalizeDefinition(array $input): NumericDefinition
    {
        $unit = is_string($input['unit'] ?? null) ? trim($input['unit']) : '';

        return new NumericDefinition(
            self::canonical($input['correct'] ?? null),
            self::canonical($input['tolerance'] ?? null),
            $unit === '' ? null : $unit,
        );
    }

    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
    {
        $definition = $this->expect($definition, NumericDefinition::class);

        return ['correct' => $definition->correct, 'tolerance' => $definition->tolerance, 'unit' => $definition->unit];
    }

    public function answerRules(QuestionDefinition $definition): array
    {
        $this->expect($definition, NumericDefinition::class);

        return [
            'value' => ['present', 'nullable', 'string', 'max:'.NumericDefinition::MAX_LENGTH, 'regex:'.self::DECIMAL_PATTERN],
        ];
    }

    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
    {
        $definition = $this->expect($definition, NumericDefinition::class);
        $value = $this->value($answer);

        if ($value === null) {
            return GradeResult::zero();
        }

        $difference = $value->minus($definition->correctValue())->abs();

        return $difference->isLessThanOrEqualTo($definition->toleranceValue()) ? GradeResult::full($points) : GradeResult::zero();
    }

    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
    {
        $definition = $this->expect($definition, NumericDefinition::class);

        return ['unit' => $definition->unit];
    }

    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
    {
        $definition = $this->expect($definition, NumericDefinition::class);

        return [
            'value' => $this->value($answer)?->toString(),
            'unit' => $definition->unit,
            ...($revealCorrect ? ['correct' => $definition->correct, 'tolerance' => $definition->tolerance] : []),
        ];
    }

    /**
     * "9,8" → "9.8"; anything else is passed through for the value object to judge.
     */
    private static function canonical(mixed $value): string
    {
        return is_string($value) ? str_replace(',', '.', trim($value)) : '';
    }

    /**
     * @param  array<array-key, mixed>|null  $answer
     */
    private function value(?array $answer): ?BigDecimal
    {
        $value = $answer['value'] ?? null;

        if (! is_string($value) || preg_match(self::DECIMAL_PATTERN, trim($value)) !== 1) {
            return null;
        }

        return BigDecimal::of(self::canonical($value));
    }
}
