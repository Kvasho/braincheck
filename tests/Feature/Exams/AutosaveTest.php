<?php

declare(strict_types=1);

use App\Enums\AntiCheatEvent;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\AttemptEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Support\ExamFixtures;

function autosaveAttempt(User $student, array $attributes = []): Attempt
{
    $test = ExamFixtures::assignedTest($student, [QuestionType::SingleChoice, QuestionType::Numeric], $attributes);
    test()->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid));

    return Attempt::query()->where('user_id', $student->id)->sole();
}

function optionIds(Attempt $attempt): array
{
    return array_column($attempt->questions()->where('position', 1)->firstOrFail()->snapshot['player']['options'], 'id');
}

function savedAnswer(Attempt $attempt, int $position): AttemptAnswer
{
    return AttemptAnswer::query()->where('attempt_question_id', $attempt->questions()->where('position', $position)->firstOrFail()->id)->sole();
}

it('saves an answer and a flag and answers only { saved_at }', function (): void {
    $student = ExamFixtures::student();
    $attempt = autosaveAttempt($student);
    $id = optionIds($attempt)[1];

    $response = $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => ['selected' => $id], 'flagged' => true])->assertOk();

    expect(array_keys($response->json()))->toBe(['saved_at'])
        ->and(savedAnswer($attempt, 1)->answer)->toBe(['selected' => $id])
        ->and(savedAnswer($attempt, 1)->flagged)->toBeTrue()
        ->and(savedAnswer($attempt, 1)->answered_at)->not->toBeNull()
        ->and($attempt->fresh()?->last_seen_at)->not->toBeNull();

    // Flag only: the answer stays; null clears it.
    $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['flagged' => false])->assertOk();
    expect(savedAnswer($attempt, 1)->answer)->toBe(['selected' => $id])->and(savedAnswer($attempt, 1)->flagged)->toBeFalse();

    $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => null])->assertOk();
    expect(savedAnswer($attempt, 1)->answer)->toBeNull()->and(savedAnswer($attempt, 1)->answered_at)->toBeNull();
});

it('ignores client-sent times, scores and status, and stores only the answer keys', function (): void {
    $student = ExamFixtures::student();
    $attempt = autosaveAttempt($student);
    $before = $attempt->fresh();

    $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), [
        'answer' => ['value' => '3.5', 'is_correct' => true, 'score' => 100],
        'deadline_at' => '2030-01-01T00:00:00Z',
        'status' => 'submitted',
        'score' => '100',
        'answered_at' => '2000-01-01',
    ])->assertOk();

    $after = $attempt->fresh();
    expect(savedAnswer($attempt, 2)->answer)->toBe(['value' => '3.5'])
        ->and(savedAnswer($attempt, 2)->is_correct)->toBeNull()
        ->and($after?->deadline_at?->equalTo($before?->deadline_at))->toBeTrue()
        ->and($after?->status->value)->toBe('in_progress')
        ->and($after?->score)->toBeNull();
});

it('validates the answer against the snapshot', function (array $answer): void {
    $student = ExamFixtures::student();
    $attempt = autosaveAttempt($student);

    $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => $answer])->assertUnprocessable();
})->with([
    'ID not in the snapshot' => [['selected' => 'ZZZZZZZZ']],
    'wrong shape' => [['selected' => ['a', 'b']]],
    'missing key' => [['other' => 'x']],
]);

it('is 404 for another student, a preview and an unknown position', function (): void {
    $owner = ExamFixtures::student();
    $attempt = autosaveAttempt($owner);

    $this->putJson(route('student.attempts.answers.update', [$attempt, 99]), ['answer' => null])->assertNotFound();

    $this->actingAs(ExamFixtures::student())
        ->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => ['selected' => 'nonsense']])
        ->assertNotFound();

    $preview = Attempt::factory()->for($attempt->test)->for($owner)->create(['is_preview' => true, 'attempt_number' => 5, 'status' => 'submitted', 'submitted_at' => now()]);
    $this->actingAs($owner)->putJson(route('student.attempts.answers.update', [$preview, 1]), ['answer' => null])->assertNotFound();
});

it('refuses a submitted attempt with 409 attempt_finished', function (): void {
    $student = ExamFixtures::student();
    $attempt = autosaveAttempt($student);
    $this->post(route('student.attempts.submit', $attempt));

    $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => null])
        ->assertStatus(409)
        ->assertJson(['code' => 'attempt_finished', 'result_url' => route('student.attempts.result', $attempt)]);
});

describe('deadline and grace', function (): void {
    it('accepts at deadline + grace − 1 s and refuses at + grace + 1 s with 409 attempt_expired', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
        $student = ExamFixtures::student();
        $attempt = autosaveAttempt($student, ['time_limit_minutes' => 10]);
        $deadline = CarbonImmutable::parse('2026-10-23 10:10:00', 'UTC');

        $this->travelTo($deadline->addSeconds(9));
        $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '1']])->assertOk();

        $this->travelTo($deadline->addSeconds(11));
        $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '2']])
            ->assertStatus(409)
            ->assertJson(['code' => 'attempt_expired']);

        $attempt->refresh();
        expect($attempt->status->value)->toBe('timed_out')
            ->and($attempt->submitted_at?->equalTo($deadline))->toBeTrue()
            ->and(savedAnswer($attempt, 2)->answer)->toBe(['value' => '1']);
    });
});

/**
 * Pins the session ID of the following requests (the test client otherwise
 * starts a new session on every request).
 */
function inSession(string $id): void
{
    // JSON test requests send cookies only with credentials (a browser's same-origin fetch does).
    test()->withCredentials()->withCookie((string) config('session.cookie'), $id);
}

it('saves from another session but records one session_changed event per new session', function (): void {
    $student = ExamFixtures::student();
    inSession(str_repeat('a', 40));
    $attempt = autosaveAttempt($student);

    inSession(str_repeat('b', 40));
    foreach (range(1, 3) as $save) {
        $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => (string) $save]])->assertOk();
    }

    inSession(str_repeat('c', 40));
    $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '4']])->assertOk();

    expect(savedAnswer($attempt, 2)->answer)->toBe(['value' => '4'])
        ->and(AttemptEvent::query()->where('attempt_id', $attempt->id)->where('type', AntiCheatEvent::SessionChanged->value)->count())->toBe(2)
        ->and(json_encode(AttemptEvent::query()->pluck('meta')))->not->toContain(hash('sha256', str_repeat('b', 40)));
});

it('records nothing while the session is the one that started', function (): void {
    $student = ExamFixtures::student();
    inSession(str_repeat('a', 40));
    $attempt = autosaveAttempt($student);

    $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '1']])->assertOk();
    $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '2']])->assertOk();

    expect(AttemptEvent::query()->count())->toBe(0);
});

it('is rate limited by autosave (60 per minute)', function (): void {
    $student = ExamFixtures::student();
    $attempt = autosaveAttempt($student);

    foreach (range(1, 60) as $save) {
        $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '1']])->assertOk();
    }

    $this->putJson(route('student.attempts.answers.update', [$attempt, 2]), ['answer' => ['value' => '1']])->assertTooManyRequests();
});
