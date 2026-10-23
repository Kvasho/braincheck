<?php

declare(strict_types=1);

namespace App\Actions\Attempts;

use App\Domain\Attempts\AnswerUpdate;
use App\Domain\Attempts\AttemptTiming;
use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\AntiCheatEvent;
use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Exceptions\AttemptNotAccepting;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptEvent;
use App\Models\AttemptQuestion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Autosave of one answer (and/or its flag). The caller has authorized the
 * owner of a real attempt; here, with the attempt row locked:
 *
 * - the attempt must be in progress and not past deadline + grace
 *   (otherwise 409: `attempt_expired` after finishing it as timed out, or
 *   `attempt_finished`);
 * - the position must exist in this attempt (404);
 * - the answer is validated with the type's answerRules() against the
 *   snapshot definition (IDs must exist in the snapshot; 422), and only the
 *   validated keys are stored;
 * - an autosave from a different session than the one that started the
 *   attempt is still saved, but records one `session_changed` event per new
 *   session (re-login is legitimate; teachers decide).
 */
final class SaveAnswer
{
    public function __construct(
        private readonly QuestionTypeRegistry $registry,
        private readonly FinishExpiredAttempt $finishExpired,
    ) {}

    /**
     * @throws AttemptNotAccepting
     * @throws ModelNotFoundException
     * @throws ValidationException
     */
    public function handle(Attempt $attempt, int $position, AnswerUpdate $update, ?string $sessionHash): CarbonImmutable
    {
        if ($this->finishExpired->handle($attempt)) {
            throw AttemptNotAccepting::expired($attempt);
        }

        return DB::transaction(function () use ($attempt, $position, $update, $sessionHash): CarbonImmutable {
            $locked = Attempt::lockedCopyOf($attempt) ?? throw (new ModelNotFoundException)->setModel(Attempt::class);
            $now = CarbonImmutable::now();

            if ($locked->status !== AttemptStatus::InProgress) {
                throw $locked->status === AttemptStatus::TimedOut ? AttemptNotAccepting::expired($locked) : AttemptNotAccepting::finished($locked);
            }

            if (AttemptTiming::isExpired($locked, $now)) {
                // Expired between the check above and the lock: attempts:expire finishes it.
                throw AttemptNotAccepting::expired($locked);
            }

            /** @var AttemptQuestion $question */
            $question = AttemptQuestion::query()
                ->where('attempt_id', $locked->id)
                ->where('position', $position)
                ->firstOrFail();

            /** @var AttemptAnswer $answer */
            $answer = AttemptAnswer::query()->where('attempt_question_id', $question->id)->lockForUpdate()->firstOrFail();

            if ($update->hasAnswer) {
                $value = $update->answer === null ? null : $this->validated($question, $update->answer);
                $answer->forceFill([
                    'answer' => $value,
                    'answered_at' => $value === null ? null : $now,
                ]);
            }

            if ($update->flagged !== null) {
                $answer->flagged = $update->flagged;
            }

            $answer->save();

            $locked->forceFill(['last_seen_at' => $now])->save();
            $this->recordSessionChange($locked, $sessionHash, $now);

            return $now;
        });
    }

    /**
     * @param  array<array-key, mixed>  $answer
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validated(AttemptQuestion $question, array $answer): array
    {
        $type = QuestionType::from((string) $question->snapshot['type']);
        $definition = Definitions::fromArray($type, (array) $question->snapshot['definition']);
        $rules = $this->registry->for($type)->answerRules($definition);

        $prefixed = ['answer' => ['required', 'array']];

        foreach ($rules as $field => $fieldRules) {
            $prefixed["answer.{$field}"] = $fieldRules;
        }

        Validator::make(['answer' => $answer], $prefixed)->validate();

        // Keep only the top-level keys the type defines (nothing extra is stored).
        $keys = array_unique(array_map(fn (string $field): string => explode('.', $field)[0], array_keys($rules)));

        /** @var array<string, mixed> $only */
        $only = Arr::only($answer, $keys);

        return $only;
    }

    private function recordSessionChange(Attempt $attempt, ?string $sessionHash, CarbonImmutable $now): void
    {
        if ($sessionHash === null || $attempt->session_hash === null || hash_equals($attempt->session_hash, $sessionHash)) {
            return;
        }

        // Only a short, non-reversible tag of the session hash: enough to count distinct sessions.
        $tag = substr($sessionHash, 0, 16);

        $known = AttemptEvent::query()
            ->where('attempt_id', $attempt->id)
            ->where('type', AntiCheatEvent::SessionChanged->value)
            ->where('meta->session', $tag)
            ->exists();

        if (! $known) {
            $event = new AttemptEvent(['type' => AntiCheatEvent::SessionChanged, 'occurred_at' => $now, 'meta' => ['session' => $tag]]);
            $event->attempt()->associate($attempt);
            $event->save();
        }
    }
}
