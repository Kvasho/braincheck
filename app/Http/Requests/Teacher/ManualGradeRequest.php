<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Manual grade: points, optional feedback (≤ 2000) and the grading version
 * the teacher loaded (conflict detection).
 */
final class ManualGradeRequest extends GradeAnswerRequest
{
    public const int FEEDBACK_MAX = 2000;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'feedback' => ['nullable', 'string', 'max:'.self::FEEDBACK_MAX],
            'version' => ['required', 'string', 'size:64'],
            'next' => ['sometimes', 'boolean'],
        ];
    }

    public function feedback(): ?string
    {
        $feedback = trim($this->string('feedback')->toString());

        return $feedback === '' ? null : $feedback;
    }
}
