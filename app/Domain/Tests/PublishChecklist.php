<?php

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\QuestionSelectionMode;
use App\Exceptions\InvalidQuestionDefinition;
use App\Models\Question;
use App\Models\Test;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;

/**
 * Everything PublishTest checks, as a list the builder's "Review & publish"
 * step can show. Errors block publishing; warnings don't.
 *
 * @phpstan-type Item array{key: string, ok: bool, severity: 'error'|'warning', message: string}
 */
final readonly class PublishChecklist
{
    public function __construct(
        private QuestionTypeRegistry $registry,
        private ValidatorFactory $validator,
    ) {}

    /**
     * @return list<Item>
     */
    public function check(Test $test): array
    {
        $questions = $test->questions()->withTrashed()->get();
        $count = $questions->count();
        $now = CarbonImmutable::now();

        $items = [
            self::item('has_questions', $count >= 1, 'error', __('The test has at least one question.')),
        ];

        if ($test->selection_mode === QuestionSelectionMode::RandomPool) {
            $draw = (int) $test->random_question_count;

            $items[] = self::item('pool_size', $draw >= 1 && $draw <= $count, 'error', __('The number of questions to draw (:draw) is between 1 and the pool size (:pool).', ['draw' => $draw, 'pool' => $count]));
        }

        $invalid = $questions->reject(fn (Question $question): bool => $this->definitionIsValid($question))->count();
        $items[] = self::item('valid_definitions', $invalid === 0, 'error', __('Every question is complete and valid.'));

        if ($test->closes_at !== null) {
            $items[] = self::item('closes_in_future', $test->closes_at->isFuture(), 'error', __('The closing time is in the future.'));
        }

        if ($test->opens_at !== null && $test->closes_at !== null) {
            $items[] = self::item('window_order', $test->closes_at->greaterThan($test->opens_at), 'error', __('The test closes after it opens.'));
        }

        $archived = $questions->filter(fn (Question $question): bool => $question->trashed())->count();
        $items[] = self::item('no_archived', $archived === 0, 'warning', __(':count archived question(s) are still attached.', ['count' => $archived]));

        if ($test->time_limit_minutes !== null && $test->closes_at !== null) {
            $start = $test->opens_at !== null && $test->opens_at->isFuture() ? $test->opens_at : $now;
            $windowMinutes = (int) floor($start->diffInMinutes($test->closes_at, false));

            $items[] = self::item('window_fits_time_limit', $windowMinutes >= $test->time_limit_minutes, 'warning', __('The test is open longer than its time limit (:limit minutes), so everyone can finish.', ['limit' => $test->time_limit_minutes]));
        }

        return $items;
    }

    /**
     * Messages of the failed errors (empty: publishable).
     *
     * @return array<string, string>
     */
    public function errors(Test $test): array
    {
        $errors = [];

        foreach ($this->check($test) as $item) {
            if (! $item['ok'] && $item['severity'] === 'error') {
                $errors[$item['key']] = $item['message'];
            }
        }

        return $errors;
    }

    /**
     * Re-validate the stored definition through its handler, as the editor would.
     */
    private function definitionIsValid(Question $question): bool
    {
        try {
            $handler = $this->registry->for($question->type);

            return ! $this->validator->make($handler->toEditorInput($question->definition), $handler->definitionRules())->fails();
        } catch (InvalidQuestionDefinition) {
            return false;
        }
    }

    /**
     * @param  'error'|'warning'  $severity
     * @return Item
     */
    private static function item(string $key, bool $ok, string $severity, string $message): array
    {
        return ['key' => $key, 'ok' => $ok, 'severity' => $severity, 'message' => $message];
    }
}
