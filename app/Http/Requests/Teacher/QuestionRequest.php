<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Questions\Points;
use App\Domain\Questions\QuestionData;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Domain\Shared\RichTextDocument;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;
use App\Models\Category;
use App\Models\Tag;
use App\Rules\RichText;
use App\Support\ImageReencoder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;

/**
 * Shared rules for creating and editing a question. The `definition` is
 * validated by the type's handler (definitionRules) and normalized by it.
 *
 * With an image upload the form is sent as multipart, so the structured
 * fields (body, explanation, definition, tags) arrive as JSON strings and are
 * decoded first; JSON keeps booleans and numbers intact.
 */
abstract class QuestionRequest extends FormRequest
{
    public const int MAX_TAGS = 20;

    public const int IMAGE_MAX_KB = 4 * 1024;

    /** Points: 0.01–9999.99, at most two decimals (decimal(6,2) column). */
    public const string POINTS_PATTERN = '/^\d{1,4}(\.\d{1,2})?$/';

    abstract protected function questionType(): ?QuestionType;

    protected function prepareForValidation(): void
    {
        $decoded = [];

        foreach (['body', 'explanation', 'definition', 'tags'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $decoded[$field] = $value === '' || $value === 'null' ? null : json_decode($value, true);
            }
        }

        $this->merge($decoded);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'body' => ['required', new RichText(required: true)],
            'explanation' => ['nullable', new RichText(required: false)],
            'category' => ['nullable', 'string', 'ulid', Rule::exists('categories', 'ulid')],
            'tags' => ['nullable', 'array', 'list', 'max:'.self::MAX_TAGS],
            'tags.*' => ['string', 'ulid', 'distinct', Rule::exists('tags', 'ulid')],
            'difficulty' => ['required', Rule::enum(Difficulty::class)],
            'points' => ['required', 'string', 'regex:'.self::POINTS_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
            'definition' => ['required', 'array'],
            'image' => [
                'nullable',
                File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(self::IMAGE_MAX_KB),
                'dimensions:max_width='.ImageReencoder::MAX_SOURCE_DIMENSION.',max_height='.ImageReencoder::MAX_SOURCE_DIMENSION,
            ],
            'remove_image' => ['sometimes', 'boolean'],
        ];

        $type = $this->questionType();

        if ($type !== null) {
            foreach (app(QuestionTypeRegistry::class)->for($type)->definitionRules() as $key => $definitionRules) {
                $rules["definition.{$key}"] = $definitionRules;
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'body' => __('question'),
            'explanation' => __('explanation'),
        ];
    }

    /**
     * @throws ValidationException
     */
    public function toData(): QuestionData
    {
        $type = $this->questionType() ?? throw ValidationException::withMessages(['type' => __('Choose a question type.')]);

        /** @var array<array-key, mixed> $body */
        $body = $this->validated('body');
        $explanation = $this->validated('explanation');
        /** @var array<array-key, mixed> $definitionInput */
        $definitionInput = $this->validated('definition');
        /** @var list<string> $tagUlids */
        $tagUlids = $this->validated('tags') ?? [];
        $categoryUlid = $this->validated('category');

        try {
            $definition = app(QuestionTypeRegistry::class)->for($type)->normalizeDefinition($definitionInput);
        } catch (InvalidQuestionDefinition $exception) {
            throw ValidationException::withMessages(['definition' => $exception->getMessage()]);
        }

        $explanationDocument = is_array($explanation) ? RichTextDocument::fromArray($explanation) : null;

        return new QuestionData(
            type: $type,
            body: RichTextDocument::fromArray($body),
            explanation: $explanationDocument?->isEmpty() === true ? null : $explanationDocument,
            category: is_string($categoryUlid) ? Category::query()->where('ulid', $categoryUlid)->first() : null,
            tags: $tagUlids === [] ? [] : array_values(Tag::query()->whereIn('ulid', $tagUlids)->get()->all()),
            difficulty: $this->enum('difficulty', Difficulty::class) ?? Difficulty::Medium,
            points: Points::of($this->string('points')->toString()),
            definition: $definition,
            image: $this->file('image') instanceof \Illuminate\Http\UploadedFile ? $this->file('image') : null,
            removeImage: $this->boolean('remove_image'),
        );
    }
}
