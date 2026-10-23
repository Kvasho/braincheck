<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Domain\Tests\QuestionLink;
use App\Domain\Tests\TestLockRules;
use App\Domain\Tests\TestSettings;
use App\Enums\QuestionSelectionMode;
use App\Enums\TestStatus;
use App\Models\Test;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the builder (explicit save): settings and, when given, the question
 * list, in one transaction. Locked tests accept only the allowed changes
 * (TestLockRules); archived tests are read-only.
 */
final class UpdateTest
{
    public function __construct(
        private readonly SyncTestQuestions $syncTestQuestions,
        private readonly ExtendTestWindow $extendTestWindow,
    ) {}

    /**
     * @param  list<QuestionLink>|null  $questions
     *
     * @throws ValidationException
     */
    public function handle(Test $test, TestSettings $settings, ?array $questions = null): Test
    {
        if ($test->status === TestStatus::Archived) {
            throw ValidationException::withMessages(['test' => __('Archived tests can not be changed.')]);
        }

        $violations = TestLockRules::violations($test, $settings, $questions);

        if ($violations !== []) {
            throw ValidationException::withMessages($violations);
        }

        if ($settings->opensAt !== null && $settings->closesAt !== null && ! $settings->closesAt->greaterThan($settings->opensAt)) {
            throw ValidationException::withMessages(['closes_at' => __('The test must close after it opens.')]);
        }

        return DB::transaction(function () use ($test, $settings, $questions): Test {
            $test->fill([
                'title' => $settings->title,
                'description' => $settings->description,
                'time_limit_minutes' => $settings->timeLimitMinutes,
                'max_attempts' => $settings->maxAttempts,
                'pass_percent' => $settings->passPercent,
                'shuffle_questions' => $settings->shuffleQuestions,
                'shuffle_options' => $settings->shuffleOptions,
                'selection_mode' => $settings->selectionMode,
                'random_question_count' => $settings->selectionMode === QuestionSelectionMode::RandomPool ? $settings->randomQuestionCount : null,
                'opens_at' => $settings->opensAt,
                'results_visibility' => $settings->resultsVisibility,
                'show_correct_answers' => $settings->showCorrectAnswers,
                'issues_certificate' => $settings->issuesCertificate,
            ]);
            $test->category()->associate($settings->category);

            if ($test->isLocked()) {
                $test->save();
                $this->extendTestWindow->handle($test, $settings->closesAt);
            } else {
                $test->closes_at = $settings->closesAt;
                $test->save();
            }

            if ($questions !== null) {
                $this->syncTestQuestions->handle($test, $questions);
            }

            return $test;
        });
    }
}
