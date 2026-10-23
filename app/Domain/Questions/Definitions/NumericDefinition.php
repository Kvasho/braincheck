<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;
use Brick\Math\BigDecimal;
use Brick\Math\Exception\MathException;

/**
 * `{ correct: "9.81", tolerance: "0.05", unit: string | null }`.
 *
 * `correct` and `tolerance` are finite decimals stored as strings (never floats)
 * and compared with brick/math: an answer is correct when
 * |answer − correct| ≤ tolerance. JSON numbers written before Stage 4 are still
 * read and converted to their exact decimal string.
 */
final readonly class NumericDefinition implements QuestionDefinition
{
    public const int MAX_LENGTH = 32;

    public string $correct;

    public string $tolerance;

    public function __construct(
        string $correct,
        string $tolerance,
        public ?string $unit,
    ) {
        $this->correct = self::decimal($correct, 'correct')->toString();
        $tolerance = self::decimal($tolerance, 'tolerance');

        if ($tolerance->isNegative()) {
            throw InvalidQuestionDefinition::because('tolerance must be zero or positive.');
        }

        $this->tolerance = $tolerance->toString();
    }

    public function type(): QuestionType
    {
        return QuestionType::Numeric;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['correct', 'tolerance', 'unit']);

        return new self(
            self::decimalString($data['correct'] ?? null, 'correct'),
            self::decimalString($data['tolerance'] ?? null, 'tolerance'),
            Guard::nullableString($data['unit'] ?? null, 'unit', 32),
        );
    }

    public function correctValue(): BigDecimal
    {
        return BigDecimal::of($this->correct);
    }

    public function toleranceValue(): BigDecimal
    {
        return BigDecimal::of($this->tolerance);
    }

    public function toArray(): array
    {
        return [
            'correct' => $this->correct,
            'tolerance' => $this->tolerance,
            'unit' => $this->unit,
        ];
    }

    /**
     * A plain decimal string ("-12.5", "0.001"). Exponents, hex, blanks,
     * NaN/INF and anything longer than MAX_LENGTH are rejected.
     *
     * @throws InvalidQuestionDefinition
     */
    public static function decimal(string $value, string $field): BigDecimal
    {
        if (strlen($value) > self::MAX_LENGTH || preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            throw InvalidQuestionDefinition::because("{$field} must be a decimal number such as \"9.81\".");
        }

        try {
            return BigDecimal::of($value);
        } catch (MathException) {
            throw InvalidQuestionDefinition::because("{$field} must be a decimal number such as \"9.81\".");
        }
    }

    /**
     * Stored value → decimal string. Legacy JSON numbers are converted with
     * their shortest exact representation (json_encode), e.g. 9.81 → "9.81".
     */
    private static function decimalString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value) && is_finite($value)) {
            $encoded = (string) json_encode($value);

            return str_contains($encoded, 'E') || str_contains($encoded, 'e')
                ? BigDecimal::of($encoded)->toString()
                : $encoded;
        }

        throw InvalidQuestionDefinition::because("{$field} must be a decimal number such as \"9.81\".");
    }
}
