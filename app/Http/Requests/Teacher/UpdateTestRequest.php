<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Actions\Tests\SyncTestQuestions;
use App\Domain\Questions\Points;
use App\Domain\Tests\QuestionLink;
use App\Domain\Tests\TestLockRules;
use App\Domain\Tests\TestSettings;
use App\Enums\QuestionSelectionMode;
use App\Enums\ResultsVisibility;
use App\Models\Category;
use App\Models\Question;
use App\Models\Test;
use App\Support\AppTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The builder's explicit save: settings and (optionally) the question list.
 *
 * - Foreign or unknown question ULIDs reject the whole request (403).
 * - Times are entered in the display timezone (Asia/Tbilisi) and stored in UTC.
 * - Locked tests: every forbidden change is a 422 on its field (TestLockRules,
 *   enforced again in UpdateTest).
 */
final class UpdateTestRequest extends FormRequest
{
    public const int TITLE_MIN = 3;

    public const int TITLE_MAX = 150;

    public const int DESCRIPTION_MAX = 2000;

    public const int MAX_ATTEMPTS = 20;

    public const int TIME_LIMIT_MAX = 600;

    public function authorize(): bool
    {
        $test = $this->test();

        if ($test === null || $this->user()?->can('update', $test) !== true) {
            return false;
        }

        $ulids = $this->questionUlids();

        // Malformed lists are a validation error (422), not an ownership question.
        if ($ulids === null || $ulids === [] || count($ulids) > SyncTestQuestions::MAX_QUESTIONS) {
            return true;
        }

        $unique = array_values(array_unique($ulids));

        return Question::withTrashed()->where('owner_id', $test->owner_id)->whereIn('ulid', $unique)->count() === count($unique);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $local = 'date_format:'.AppTime::INPUT_FORMAT;

        return [
            'title' => ['required', 'string', 'min:'.self::TITLE_MIN, 'max:'.self::TITLE_MAX],
            'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            'category' => ['nullable', 'string', 'ulid', Rule::exists('categories', 'ulid')],
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:'.self::TIME_LIMIT_MAX],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ATTEMPTS],
            'pass_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'shuffle_questions' => ['required', 'boolean'],
            'shuffle_options' => ['required', 'boolean'],
            'selection_mode' => ['required', Rule::enum(QuestionSelectionMode::class)],
            'random_question_count' => ['nullable', 'required_if:selection_mode,random_pool', 'integer', 'min:1', 'max:'.SyncTestQuestions::MAX_QUESTIONS],
            'opens_at' => ['nullable', 'string', $local],
            'closes_at' => ['nullable', 'string', $local],
            'results_visibility' => ['required', Rule::enum(ResultsVisibility::class)],
            'show_correct_answers' => ['required', 'boolean'],
            'issues_certificate' => ['required', 'boolean'],
            'questions' => ['sometimes', 'array', 'list', 'max:'.SyncTestQuestions::MAX_QUESTIONS],
            'questions.*' => ['required', 'array:ulid,points_override'],
            'questions.*.ulid' => ['required', 'string', 'ulid', 'distinct'],
            'questions.*.points_override' => ['nullable', 'string', 'regex:'.QuestionRequest::POINTS_PATTERN, 'not_regex:/^0+(\.0+)?$/'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $test = $this->test();
            $settings = $this->settings();
            $questions = $this->questionLinks();

            if ($settings->opensAt !== null && $settings->closesAt !== null && ! $settings->closesAt->greaterThan($settings->opensAt)) {
                $validator->errors()->add('closes_at', __('The test must close after it opens.'));
            }

            $poolSize = $questions === null ? ($test?->questions()->withTrashed()->count() ?? 0) : count($questions);

            if ($settings->selectionMode === QuestionSelectionMode::RandomPool && (int) $settings->randomQuestionCount > $poolSize) {
                $validator->errors()->add('random_question_count', __('Draw at most :pool questions (the pool size).', ['pool' => $poolSize]));
            }

            if ($test !== null) {
                foreach (TestLockRules::violations($test, $settings, $questions) as $field => $message) {
                    $validator->errors()->add($field, $message);
                }
            }
        }];
    }

    public function settings(): TestSettings
    {
        $category = $this->string('category')->toString();
        $opens = $this->string('opens_at')->toString();
        $closes = $this->string('closes_at')->toString();
        $description = trim($this->string('description')->toString());
        $mode = $this->enum('selection_mode', QuestionSelectionMode::class) ?? QuestionSelectionMode::Fixed;

        return new TestSettings(
            title: trim($this->string('title')->toString()),
            description: $description === '' ? null : $description,
            category: $category === '' ? null : Category::query()->where('ulid', $category)->first(),
            timeLimitMinutes: $this->filled('time_limit_minutes') ? $this->integer('time_limit_minutes') : null,
            maxAttempts: $this->filled('max_attempts') ? $this->integer('max_attempts') : null,
            passPercent: $this->integer('pass_percent'),
            shuffleQuestions: $this->boolean('shuffle_questions'),
            shuffleOptions: $this->boolean('shuffle_options'),
            selectionMode: $mode,
            randomQuestionCount: $mode === QuestionSelectionMode::RandomPool && $this->filled('random_question_count') ? $this->integer('random_question_count') : null,
            opensAt: $opens === '' ? null : AppTime::fromLocalInput($opens),
            closesAt: $closes === '' ? null : AppTime::fromLocalInput($closes),
            resultsVisibility: $this->enum('results_visibility', ResultsVisibility::class) ?? ResultsVisibility::Immediately,
            showCorrectAnswers: $this->boolean('show_correct_answers'),
            issuesCertificate: $this->boolean('issues_certificate'),
        );
    }

    /**
     * The question list in order, or null when the request doesn't change it.
     *
     * @return list<QuestionLink>|null
     */
    public function questionLinks(): ?array
    {
        if (! $this->has('questions')) {
            return null;
        }

        /** @var list<array{ulid: string, points_override?: string|null}> $rows */
        $rows = $this->input('questions', []);

        return array_map(
            fn (array $row): QuestionLink => new QuestionLink(
                $row['ulid'],
                isset($row['points_override']) && $row['points_override'] !== '' ? Points::of($row['points_override']) : null,
            ),
            $rows,
        );
    }

    /**
     * @return list<string>|null
     */
    private function questionUlids(): ?array
    {
        $rows = $this->input('questions');

        if (! is_array($rows)) {
            return $this->has('questions') ? null : [];
        }

        $ulids = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['ulid'] ?? null)) {
                return null;
            }

            $ulids[] = $row['ulid'];
        }

        return $ulids;
    }

    private function test(): ?Test
    {
        $test = $this->route('test');

        return $test instanceof Test ? $test : null;
    }
}
