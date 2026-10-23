<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Models\Question;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Check an answer in the owner's preview. The answer is validated exactly like
 * a student's (the handler's answerRules for this definition).
 */
final class PreviewGradeRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // The preview sends the answer as JSON text (any shape, booleans intact).
        if (is_string($this->input('answer'))) {
            $this->merge(['answer' => json_decode($this->input('answer'), true)]);
        }
    }

    public function authorize(): bool
    {
        $question = $this->question();

        return $question !== null && $this->user()?->can('previewGrade', $question) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['answer' => ['present', 'nullable', 'array']];
        $question = $this->question();

        if ($question !== null && is_array($this->input('answer'))) {
            foreach (app(QuestionTypeRegistry::class)->for($question->type)->answerRules($question->definition) as $key => $answerRules) {
                $rules["answer.{$key}"] = $answerRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function answer(): ?array
    {
        $answer = $this->input('answer');

        return is_array($answer) ? $answer : null;
    }

    private function question(): ?Question
    {
        $question = $this->route('question');

        return $question instanceof Question ? $question : null;
    }
}
