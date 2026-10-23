<?php

declare(strict_types=1);

namespace App\Actions\Grading;

use App\Actions\Audit\RecordAuditEvent;
use App\Domain\Grading\AttemptTotals;
use App\Domain\Grading\SnapshotGrader;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Points;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\AttemptStatus;
use App\Enums\AuditAction;
use App\Enums\GradingStatus;
use App\Jobs\RegradeTestQuestionJob;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fixes a question's correct answer **in the snapshots of one test's
 * attempts** and regrades every affected attempt.
 *
 * THIS IS THE ONLY CODE PATH ALLOWED TO MODIFY `attempt_questions.snapshot`
 * (tests/Arch/SnapshotImmutabilityTest.php). Snapshots are history: grading,
 * review and certificates read them. A regrade changes only the
 * `definition` (correctness), never what students saw: the new definition
 * must keep exactly the same option / item IDs.
 *
 * Scope: one test, one question, one snapshot version (a question edited
 * between attempts has several versions), real attempts only. Finished
 * attempts are regraded (RegradeAttempt); attempts in progress just get the
 * fixed definition and are graded with it on submit. Queued in chunks
 * (RegradeTestQuestionJob, started by handle()); audited as `grading.regrade`
 * with a summary.
 */
final class RegradeTestQuestion
{
    public const int CHUNK = 50;

    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly RegradeAttempt $regradeAttempt,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    /**
     * The snapshots this regrade touches.
     *
     * @return Builder<AttemptQuestion>
     */
    public function affected(Test $test, int $questionId, int $version): Builder
    {
        return AttemptQuestion::query()
            ->where('question_id', $questionId)
            ->where('snapshot->version', $version)
            ->whereIn('attempt_id', Attempt::query()->real()->where('test_id', $test->id)->select('id'));
    }

    /**
     * What would change, without writing anything: per attempt the old and
     * new score and pass result.
     *
     * @return array{changes: list<array{attempt: string, student: string, status: string, old_score: string|null, new_score: string|null, old_passed: bool|null, new_passed: bool|null}>, summary: array{affected: int, changed: int, passed_flips: int}}
     *
     * @throws ValidationException
     */
    public function preview(Test $test, int $questionId, int $version, QuestionDefinition $definition): array
    {
        $rows = $this->affected($test, $questionId, $version)
            ->with(['attempt.user:id,name', 'attempt.test:id,pass_percent', 'attempt.questions.answer'])
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages(['question' => __('No attempts use this question version.')]);
        }

        $this->ensureCompatible($rows->firstOrFail(), $definition);
        $attempts = [];

        foreach ($rows as $row) {
            /** @var AttemptQuestion $row */
            $attempt = $row->attempt;
            $finished = $attempt->status !== AttemptStatus::InProgress && $attempt->grading_status !== GradingStatus::PendingAuto;
            $new = null;
            $newPassed = null;

            if ($finished) {
                $totals = AttemptTotals::of($attempt);
                $answer = $attempt->questions->firstWhere('id', $row->id)?->answer;
                $old = Points::of($answer->awarded_points ?? '0');
                $keepsManualGrade = $answer !== null && $answer->needs_manual_grading && $answer->graded_at !== null;
                $regraded = $keepsManualGrade ? $old : $this->gradeWith($row, $definition, $answer?->answer);
                $score = $totals->score->minus($old)->plus($regraded);
                $new = $score->toString();
                $newPassed = $totals->pending ? null : AttemptTotals::percent($score, $totals->max)->isGreaterThanOrEqualTo($attempt->test->pass_percent);
            }

            $attempts[] = [
                'attempt' => $attempt->ulid,
                'student' => $attempt->user->name,
                'status' => $attempt->status->value,
                'old_score' => $attempt->score,
                'new_score' => $new,
                'old_passed' => $attempt->passed,
                'new_passed' => $finished ? $newPassed : null,
            ];
        }

        $changed = array_filter($attempts, fn (array $a): bool => $a['new_score'] !== null && $a['new_score'] !== $a['old_score']);
        $flips = array_filter($attempts, fn (array $a): bool => $a['new_score'] !== null && $a['old_passed'] !== $a['new_passed']);

        return [
            'changes' => $attempts,
            'summary' => ['affected' => count($attempts), 'changed' => count($changed), 'passed_flips' => count($flips)],
        ];
    }

    /**
     * Audits the regrade and queues it. Returns the preview summary.
     *
     * @return array{affected: int, changed: int, passed_flips: int}
     *
     * @throws ValidationException
     */
    public function handle(User $actor, Test $test, int $questionId, int $version, QuestionDefinition $definition): array
    {
        $summary = $this->preview($test, $questionId, $version, $definition)['summary'];

        $this->recordAuditEvent->handle(AuditAction::GradingRegrade, $actor, $test, [
            'question_id' => $questionId,
            'version' => $version,
            ...$summary,
        ]);

        dispatch(new RegradeTestQuestionJob($test->id, $questionId, $version, $definition->type()->value, $definition->toArray()));

        return $summary;
    }

    /**
     * One snapshot: write the fixed definition and regrade the attempt if it
     * is finished. Called by the job, chunk by chunk.
     */
    public function applyTo(AttemptQuestion $row, QuestionDefinition $definition): void
    {
        DB::transaction(function () use ($row, $definition): void {
            $attempt = Attempt::lockedCopyOf($row->attempt);
            /** @var AttemptQuestion|null $locked */
            $locked = AttemptQuestion::query()->whereKey($row->id)->lockForUpdate()->first();

            if ($attempt === null || $locked === null) {
                return;
            }

            $locked->forceFill([
                'snapshot' => [
                    ...$locked->snapshot,
                    'definition' => $definition->toArray(),
                    'regraded_at' => CarbonImmutable::now()->toIso8601String(),
                ],
            ])->save();

            $this->regradeAttempt->handle($attempt, [$locked->position]);
        });
    }

    /**
     * The fixed definition may change correctness only: same type, and
     * exactly the IDs the students saw.
     *
     * @throws ValidationException
     */
    public function ensureCompatible(AttemptQuestion $sample, QuestionDefinition $definition): void
    {
        $type = SnapshotGrader::type($sample);

        if ($definition->type() !== $type) {
            throw ValidationException::withMessages(['definition' => __('The question type can not change in a regrade.')]);
        }

        $handler = $this->registry->for($type);
        $before = self::ids($handler->toEditorInput(SnapshotGrader::definition($sample)));
        $after = self::ids($handler->toEditorInput($definition));

        if ($before !== $after) {
            throw ValidationException::withMessages(['definition' => __('A regrade may only change which answers are correct, not the options students saw.')]);
        }
    }

    /**
     * @param  array<array-key, mixed>|null  $answer
     */
    private function gradeWith(AttemptQuestion $row, QuestionDefinition $definition, ?array $answer): Points
    {
        return $this->registry->for(SnapshotGrader::type($row))
            ->grade($definition, $answer, Points::of($row->points))
            ->awardedPoints;
    }

    /**
     * Every `id` in an editor input, sorted.
     *
     * @param  array<array-key, mixed>  $input
     * @return list<string>
     */
    private static function ids(array $input): array
    {
        $ids = [];

        array_walk_recursive($input, function (mixed $value, string|int $key) use (&$ids): void {
            if ($key === 'id' && is_string($value)) {
                $ids[] = $value;
            }
        });

        sort($ids);

        return $ids;
    }
}
