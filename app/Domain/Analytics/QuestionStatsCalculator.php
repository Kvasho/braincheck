<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

use App\Domain\Grading\SnapshotGrader;
use App\Domain\Shared\RichTextDocument;
use App\Enums\GradingStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Test;
use App\Models\TestQuestionStat;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Rebuilds test_question_stats for one test from its real, graded attempts:
 * per question (by question_id, stable across snapshots) the difficulty,
 * discrimination, average points, number of manual grades and, for choice
 * types, the distractor analysis. Reads snapshots only.
 */
final class QuestionStatsCalculator
{
    public const int EXCERPT_LENGTH = 200;

    public function recompute(Test $test): void
    {
        $scores = Attempt::query()->real()
            ->where('test_id', $test->id)
            ->where('grading_status', GradingStatus::Graded->value)
            ->pluck('score_percent', 'id');

        /** @var Collection<int, Collection<int, AttemptQuestion>> $byQuestion */
        $byQuestion = AttemptQuestion::query()
            ->whereIn('attempt_id', $scores->keys())
            ->whereNotNull('question_id')
            ->with('answer')
            ->get()
            ->groupBy('question_id');

        $now = CarbonImmutable::now();
        $rows = [];

        foreach ($byQuestion as $questionId => $answers) {
            $rows[] = $this->row($test, (int) $questionId, $answers, $scores->all(), $now);
        }

        DB::transaction(function () use ($test, $rows): void {
            TestQuestionStat::query()->where('test_id', $test->id)->delete();

            foreach ($rows as $row) {
                $stat = new TestQuestionStat;
                $stat->forceFill($row)->save();
            }
        });
    }

    /**
     * @param  Collection<int, AttemptQuestion>  $answers
     * @param  array<int, mixed>  $scores  attempt id => score_percent
     * @return array<string, mixed>
     */
    private function row(Test $test, int $questionId, Collection $answers, array $scores, CarbonImmutable $now): array
    {
        /** @var AttemptQuestion $latest */
        $latest = $answers->sortByDesc('id')->first();
        $type = SnapshotGrader::type($latest);
        $responses = [];
        $selections = [];
        $awarded = BigDecimal::zero();
        $manual = 0;

        foreach ($answers as $row) {
            $answer = $row->answer;
            $points = BigDecimal::of($row->points);
            $got = BigDecimal::of($answer->awarded_points ?? '0');
            $score = (string) ($scores[$row->attempt_id] ?? '0');

            $responses[] = [
                'score' => $score,
                'share' => $points->isZero() ? '0' : $got->dividedBy($points, 4, RoundingMode::HalfUp)->toString(),
            ];
            $selections[] = ['score' => $score, 'selected' => self::selected($answer?->answer)];
            $awarded = $awarded->plus($got);
            $manual += ($answer !== null && $answer->needs_manual_grading && $answer->graded_by !== null) ? 1 : 0;
        }

        $analysis = ItemAnalysis::analyse($responses);

        return [
            'test_id' => $test->id,
            'question_id' => $questionId,
            'type' => $type->value,
            'excerpt' => Str::limit(RichTextDocument::fromArray((array) $latest->snapshot['body'])->plainText, self::EXCERPT_LENGTH),
            'responses' => count($responses),
            'difficulty' => $analysis['difficulty'],
            'discrimination' => $analysis['discrimination'],
            'average_points' => $awarded->dividedBy(count($responses), 2, RoundingMode::HalfUp)->toString(),
            'manual_grades' => $manual,
            'needs_review' => $analysis['needs_review'],
            'distractors' => in_array($type, [QuestionType::SingleChoice, QuestionType::MultipleChoice], true)
                ? DistractorAnalysis::analyse(self::options($latest), $selections)
                : null,
            'computed_at' => $now,
        ];
    }

    /**
     * The options of a choice question with their correctness (teacher view).
     *
     * @return list<array{id: string, text: string, correct: bool}>
     */
    private static function options(AttemptQuestion $question): array
    {
        $definition = (array) $question->snapshot['definition'];
        $correct = (array) $definition['correct'];

        return array_values(array_map(fn (array $option): array => [
            'id' => (string) $option['id'],
            'text' => (string) $option['text'],
            'correct' => in_array($option['id'], $correct, true),
        ], (array) $definition['options']));
    }

    /**
     * Option IDs an answer selected (single: one, multiple: several).
     *
     * @param  array<array-key, mixed>|null  $answer
     * @return list<string>
     */
    private static function selected(?array $answer): array
    {
        $selected = $answer['selected'] ?? null;

        return array_values(array_filter(is_array($selected) ? $selected : [$selected], 'is_string'));
    }
}
