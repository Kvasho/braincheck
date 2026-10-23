<?php

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Questions\Points;
use App\Models\Question;
use App\Models\Test;
use Carbon\CarbonImmutable;

/**
 * What may change once a test is locked (its first real attempt started), so
 * results stay fair and comparable. Used by UpdateTestRequest (422) and by
 * the Actions (defence in depth).
 *
 * Allowed: title, description, category, extending `closes_at` (only later),
 * raising `max_attempts`, turning `issues_certificate` / `show_correct_answers`
 * on, results visibility that reveals more.
 * Not allowed: questions, points, pool size, selection mode, time limit,
 * lowering max attempts, pass percent, shuffle settings, `opens_at`.
 */
final class TestLockRules
{
    /**
     * Field => message for every forbidden change; empty when allowed (or not locked).
     *
     * @param  list<QuestionLink>|null  $questions  null = the question list is not being changed
     * @return array<string, string>
     */
    public static function violations(Test $test, TestSettings $settings, ?array $questions): array
    {
        if (! $test->isLocked()) {
            return [];
        }

        $locked = __('This setting is locked because students have already taken the test.');
        $violations = [];

        $unchanged = [
            'time_limit_minutes' => $test->time_limit_minutes === $settings->timeLimitMinutes,
            'pass_percent' => $test->pass_percent === $settings->passPercent,
            'shuffle_questions' => $test->shuffle_questions === $settings->shuffleQuestions,
            'shuffle_options' => $test->shuffle_options === $settings->shuffleOptions,
            'selection_mode' => $test->selection_mode === $settings->selectionMode,
            'random_question_count' => $test->random_question_count === $settings->randomQuestionCount,
            'opens_at' => self::sameTime($test->opens_at, $settings->opensAt),
        ];

        foreach ($unchanged as $field => $same) {
            if (! $same) {
                $violations[$field] = $locked;
            }
        }

        // Max attempts: only more (null = unlimited is the most).
        if ($settings->maxAttempts !== null && ($test->max_attempts === null || $settings->maxAttempts < $test->max_attempts)) {
            $violations['max_attempts'] = __('Once students have taken the test, attempts can only be increased.');
        }

        // Closing time: only later (null = never closes is the latest).
        if ($settings->closesAt !== null && ($test->closes_at === null || $settings->closesAt->lessThan($test->closes_at))) {
            $violations['closes_at'] = __('Once students have taken the test, it can only close later.');
        }

        if ($settings->resultsVisibility->revealRank() < $test->results_visibility->revealRank()) {
            $violations['results_visibility'] = __('Students may already have seen their results: visibility can only reveal more.');
        }

        if ($test->show_correct_answers && ! $settings->showCorrectAnswers) {
            $violations['show_correct_answers'] = __('Students may already have seen the correct answers.');
        }

        if ($test->issues_certificate && ! $settings->issuesCertificate) {
            $violations['issues_certificate'] = __('Certificates may already have been issued.');
        }

        if ($questions !== null && ! self::sameQuestions($test, $questions)) {
            $violations['questions'] = $locked;
        }

        return $violations;
    }

    /**
     * @param  list<QuestionLink>  $questions
     */
    public static function sameQuestions(Test $test, array $questions): bool
    {
        $current = $test->questions()->withTrashed()->get()
            ->map(fn (Question $question): string => $question->ulid.'|'.self::override($question))
            ->values()
            ->all();

        $given = array_map(fn (QuestionLink $link): string => $link->questionUlid.'|'.($link->pointsOverride?->toString() ?? ''), $questions);

        return $current === $given;
    }

    private static function override(Question $question): string
    {
        /** @var object{points_override: string|null} $pivot */
        $pivot = $question->getRelation('pivot');

        return $pivot->points_override === null ? '' : Points::of((string) $pivot->points_override)->toString();
    }

    private static function sameTime(?CarbonImmutable $a, ?CarbonImmutable $b): bool
    {
        return ($a === null && $b === null) || ($a !== null && $b !== null && $a->equalTo($b));
    }
}
