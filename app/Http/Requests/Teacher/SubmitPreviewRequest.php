<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Finish a preview attempt. `answers` (JSON text, position => answer) are
 * validated per question against its snapshot, exactly like a student's.
 */
final class SubmitPreviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('answers'))) {
            $this->merge(['answers' => json_decode($this->input('answers'), true)]);
        }
    }

    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt instanceof Attempt && $this->user()?->can('preview', $attempt->test) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['answers' => ['present', 'array']];
        $attempt = $this->route('attempt');
        $answers = $this->input('answers');

        if (! $attempt instanceof Attempt || ! is_array($answers)) {
            return $rules;
        }

        $registry = app(QuestionTypeRegistry::class);

        foreach ($attempt->questions()->get() as $row) {
            /** @var AttemptQuestion $row */
            $key = (string) $row->position;

            if (! is_array($answers[$key] ?? null)) {
                continue;
            }

            $type = QuestionType::from((string) $row->snapshot['type']);
            $definition = Definitions::fromArray($type, (array) $row->snapshot['definition']);

            foreach ($registry->for($type)->answerRules($definition) as $field => $answerRules) {
                $rules["answers.{$key}.{$field}"] = $answerRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<array-key, array<array-key, mixed>|null>
     */
    public function answers(): array
    {
        /** @var array<array-key, array<array-key, mixed>|null> $answers */
        $answers = array_map(fn (mixed $answer): ?array => is_array($answer) ? $answer : null, (array) $this->input('answers', []));

        return $answers;
    }
}
