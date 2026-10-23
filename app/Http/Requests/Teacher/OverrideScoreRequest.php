<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Override of an auto-graded answer: points and a mandatory reason (audited).
 */
final class OverrideScoreRequest extends GradeAnswerRequest
{
    public const int REASON_MAX = 500;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => ['required', 'string', 'min:3', 'max:'.self::REASON_MAX],
        ];
    }

    public function reason(): string
    {
        return trim($this->string('reason')->toString());
    }
}
