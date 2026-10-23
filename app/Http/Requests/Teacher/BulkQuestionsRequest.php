<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\Permission;
use App\Models\Question;
use App\Models\Tag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Bulk add/remove tags or archive. Every question ULID must belong to the
 * current teacher (and not be archived): a single foreign or unknown ULID
 * rejects the whole request with 403; nothing is processed partially.
 */
final class BulkQuestionsRequest extends FormRequest
{
    public const int MAX_QUESTIONS = 100;

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can(Permission::QuestionsManageOwn->value)) {
            return false;
        }

        $ulids = $this->input('questions');

        // Malformed input is a validation error (422), not an ownership question.
        if (! is_array($ulids) || $ulids === [] || count($ulids) > self::MAX_QUESTIONS
            || array_filter($ulids, fn (mixed $ulid): bool => ! is_string($ulid)) !== []) {
            return true;
        }

        $unique = array_values(array_unique($ulids));

        return Question::query()->ownedBy($user)->whereIn('ulid', $unique)->count() === count($unique);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['tag', 'untag', 'archive'])],
            'questions' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_QUESTIONS],
            'questions.*' => ['required', 'string', 'ulid', 'distinct'],
            'tags' => ['exclude_if:action,archive', 'required', 'array', 'list', 'min:1', 'max:'.QuestionRequest::MAX_TAGS],
            'tags.*' => ['string', 'ulid', 'distinct', Rule::exists('tags', 'ulid')],
        ];
    }

    /**
     * @return list<Question>
     */
    public function questions(): array
    {
        /** @var list<string> $ulids */
        $ulids = $this->validated('questions');
        $user = $this->user() ?? abort(403);

        return array_values(Question::query()->ownedBy($user)->whereIn('ulid', $ulids)->get()->all());
    }

    /**
     * @return list<Tag>
     */
    public function tags(): array
    {
        /** @var list<string> $ulids */
        $ulids = $this->validated('tags') ?? [];

        return array_values(Tag::query()->whereIn('ulid', $ulids)->get()->all());
    }
}
