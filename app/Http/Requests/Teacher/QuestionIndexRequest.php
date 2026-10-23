<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\Difficulty;
use App\Enums\Permission;
use App\Enums\QuestionType;
use App\Http\Requests\ListingRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the question listings (teacher bank; the admin browse extends it).
 */
class QuestionIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::QuestionsViewOwn->value) === true;
    }

    protected function filterRules(): array
    {
        return [
            'category' => ['nullable', 'string', 'ulid'],
            'tags' => ['nullable', 'array', 'list', 'max:20'],
            'tags.*' => ['string', 'ulid', 'distinct'],
            'type' => ['nullable', Rule::enum(QuestionType::class)],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'archived' => ['nullable', Rule::in(['only', 'with'])],
        ];
    }

    /**
     * @return array{category: string|null, tags: list<string>, type: QuestionType|null, difficulty: Difficulty|null, archived: string|null}
     */
    public function filters(): array
    {
        /** @var list<string> $tags */
        $tags = array_values(array_unique($this->validated('tags') ?? []));

        return [
            'category' => $this->string('category')->toString() ?: null,
            'tags' => $tags,
            'type' => $this->enum('type', QuestionType::class),
            'difficulty' => $this->enum('difficulty', Difficulty::class),
            'archived' => $this->string('archived')->toString() ?: null,
        ];
    }
}
