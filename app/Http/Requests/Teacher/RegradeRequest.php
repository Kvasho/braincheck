<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Actions\Grading\RegradeTestQuestion;
use App\Domain\Grading\SnapshotGrader;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Exceptions\InvalidQuestionDefinition;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * A regrade (preview or start): the question (ULID), the snapshot version
 * and the fixed definition (editor input, JSON text), validated by the
 * snapshot type's handler like the question editor does.
 */
final class RegradeRequest extends FormRequest
{
    private ?AttemptQuestion $sample = null;

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('definition'))) {
            $this->merge(['definition' => json_decode($this->input('definition'), true)]);
        }
    }

    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof Test && $this->user()?->can('regrade', $test) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'question' => ['required', 'string', 'ulid'],
            'version' => ['required', 'integer', 'min:1'],
            'definition' => ['required', 'array'],
        ];

        $sample = $this->sample();

        if ($sample !== null) {
            foreach (app(QuestionTypeRegistry::class)->for(SnapshotGrader::type($sample))->definitionRules() as $key => $definitionRules) {
                $rules["definition.{$key}"] = $definitionRules;
            }
        }

        return $rules;
    }

    public function test(): Test
    {
        /** @var Test $test */
        $test = $this->route('test');

        return $test;
    }

    public function questionId(): int
    {
        return (int) Question::query()->withTrashed()->where('ulid', $this->string('question')->toString())->value('id');
    }

    public function version(): int
    {
        return $this->integer('version');
    }

    /**
     * @throws ValidationException
     */
    public function definition(): QuestionDefinition
    {
        $sample = $this->sample() ?? throw ValidationException::withMessages(['question' => __('No attempts use this question version.')]);

        /** @var array<array-key, mixed> $input */
        $input = $this->validated('definition');

        try {
            return app(QuestionTypeRegistry::class)->for(SnapshotGrader::type($sample))->normalizeDefinition($input);
        } catch (InvalidQuestionDefinition $exception) {
            throw ValidationException::withMessages(['definition' => $exception->getMessage()]);
        }
    }

    /**
     * One affected snapshot (they all share the type and the version).
     */
    private function sample(): ?AttemptQuestion
    {
        if ($this->sample === null && is_string($this->input('question')) && is_numeric($this->input('version'))) {
            $this->sample = app(RegradeTestQuestion::class)
                ->affected($this->test(), $this->questionId(), (int) $this->input('version'))
                ->first();
        }

        return $this->sample;
    }
}
