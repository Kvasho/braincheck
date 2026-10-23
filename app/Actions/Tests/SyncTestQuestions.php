<?php

declare(strict_types=1);

namespace App\Actions\Tests;

use App\Domain\Tests\QuestionLink;
use App\Domain\Tests\TestLockRules;
use App\Models\Question;
use App\Models\Test;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a test's question list (order = positions 1..n, contiguous).
 *
 * - Every question must belong to the test's owner; a single foreign or unknown
 *   ULID rejects the whole request (403), nothing is applied.
 * - Archived questions may stay if already attached, but can't be newly added.
 * - Points overrides are > 0 (Points guarantees it).
 * - A locked test's questions never change.
 */
final class SyncTestQuestions
{
    public const int MAX_QUESTIONS = 200;

    /**
     * @param  list<QuestionLink>  $links
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(Test $test, array $links): void
    {
        if ($test->isLocked()) {
            if (TestLockRules::sameQuestions($test, $links)) {
                return;
            }

            throw ValidationException::withMessages(['questions' => __('This setting is locked because students have already taken the test.')]);
        }

        $ulids = array_map(fn (QuestionLink $link): string => $link->questionUlid, $links);

        if (count($ulids) !== count(array_unique($ulids))) {
            throw ValidationException::withMessages(['questions' => __('Each question can be added only once.')]);
        }

        if (count($links) > self::MAX_QUESTIONS) {
            throw ValidationException::withMessages(['questions' => __('A test can have at most :max questions.', ['max' => self::MAX_QUESTIONS])]);
        }

        DB::transaction(function () use ($test, $links, $ulids): void {
            $questions = Question::withTrashed()
                ->where('owner_id', $test->owner_id)
                ->whereIn('ulid', $ulids)
                ->get()
                ->keyBy('ulid');

            if ($questions->count() !== count($ulids)) {
                throw new AuthorizationException(__('You can only add your own questions.'));
            }

            $attached = $test->questions()->withTrashed()->pluck('questions.id')->all();

            foreach ($questions as $question) {
                if ($question->trashed() && ! in_array($question->id, $attached, true)) {
                    throw ValidationException::withMessages(['questions' => __('Archived questions can not be added to a test.')]);
                }
            }

            $rows = [];

            foreach ($links as $index => $link) {
                $rows[$questions[$link->questionUlid]->id] = [
                    'position' => $index + 1,
                    'points_override' => $link->pointsOverride?->toString(),
                ];
            }

            // Detach first: the unique (test_id, position) index forbids swapping positions in place.
            $test->questions()->detach();
            $test->questions()->attach($rows);
            $test->touch();
        });
    }
}
