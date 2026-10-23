<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\QuestionType;
use App\Models\Question;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class StoreQuestionRequest extends QuestionRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Question::class) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(QuestionType::class)],
            ...parent::rules(),
        ];
    }

    protected function questionType(): ?QuestionType
    {
        return $this->enum('type', QuestionType::class);
    }
}
