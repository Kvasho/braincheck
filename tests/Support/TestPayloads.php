<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Question;
use App\Models\Test;
use App\Support\AppTime;

/**
 * Builder save payloads for the test builder feature tests.
 */
final class TestPayloads
{
    /**
     * The builder's save payload: the test's current values, then overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function payload(Test $test, array $overrides = []): array
    {
        return [
            'title' => $test->title,
            'description' => $test->description,
            'category' => null,
            'time_limit_minutes' => $test->time_limit_minutes,
            'max_attempts' => $test->max_attempts,
            'pass_percent' => $test->pass_percent,
            'shuffle_questions' => $test->shuffle_questions,
            'shuffle_options' => $test->shuffle_options,
            'selection_mode' => $test->selection_mode->value,
            'random_question_count' => $test->random_question_count,
            'opens_at' => AppTime::toLocalInput($test->opens_at),
            'closes_at' => AppTime::toLocalInput($test->closes_at),
            'results_visibility' => $test->results_visibility->value,
            'show_correct_answers' => $test->show_correct_answers,
            'issues_certificate' => $test->issues_certificate,
            ...$overrides,
        ];
    }

    /**
     * @param  list<Question>  $questions
     * @param  array<int, string>  $overrides  index => points override
     * @return list<array{ulid: string, points_override: string|null}>
     */
    public static function links(array $questions, array $overrides = []): array
    {
        return array_map(
            fn (Question $question, int $i): array => ['ulid' => $question->ulid, 'points_override' => $overrides[$i] ?? null],
            $questions,
            array_keys($questions),
        );
    }
}
