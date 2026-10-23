<?php

declare(strict_types=1);

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Attempt;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExamFixtures;

/*
 * End-to-end over HTTP, like the browser: only what the client receives is
 * used to answer. (A Pest browser test needs the pest-plugin-browser package,
 * which can't be installed here; this covers the same requests.)
 */
it('starts, answers all 7 types, reloads, flags and submits', function (): void {
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, QuestionType::cases(), ['shuffle_options' => true, 'shuffle_questions' => true]);

    $this->actingAs($student)->get(route('student.exams.show', $test->ulid))->assertOk();
    $this->post(route('student.exams.attempts.store', $test->ulid))->assertRedirect();
    $attempt = Attempt::query()->sole();

    $player = $this->get(route('student.attempts.show', $attempt))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('exam/attempt')->has('attempt.questions', 7))
        ->viewData('page')['props']['attempt'];

    $sent = [];
    foreach ($player['questions'] as $question) {
        $answer = ExamFixtures::answerFromPlayer(QuestionType::from($question['type']), $question['player']);
        $sent[$question['position']] = $answer;

        $this->putJson(route('student.attempts.answers.update', [$attempt, $question['position']]), ['answer' => $answer])->assertOk();
    }

    $this->putJson(route('student.attempts.answers.update', [$attempt, 3]), ['flagged' => true])->assertOk();

    // Reload mid-exam: same questions, same order, answers and flag restored.
    $reloaded = $this->get(route('student.attempts.show', $attempt))->viewData('page')['props']['attempt'];

    expect($reloaded['questions'])->toBe($player['questions'])
        ->and(collect($reloaded['saved'])->mapWithKeys(fn (array $saved): array => [$saved['position'] => $saved['value']])->all())->toEqual($sent)
        ->and(collect($reloaded['saved'])->where('flagged', true)->pluck('position')->all())->toBe([3])
        ->and(collect($reloaded['saved'])->every(fn (array $saved): bool => $saved['answered']))->toBeTrue();

    $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));

    expect($attempt->fresh()?->status)->toBe(AttemptStatus::Submitted);
    $this->get(route('student.attempts.show', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
    // One of two attempts used: available again for a second attempt.
    $this->get(route('student.exams.index'))->assertInertia(fn (Assert $page) => $page->where('exams.0.section', 'available')->where('exams.0.attempts_used', 1));
});

it('auto-submits when the time limit runs out and sends the student to the result', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:00:00', 'UTC'));
    $student = ExamFixtures::student();
    $test = ExamFixtures::assignedTest($student, [QuestionType::Numeric], ['time_limit_minutes' => 1]);

    $this->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid));
    $attempt = Attempt::query()->sole();
    $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => ['value' => '7']])->assertOk();

    // The client's timer hits 0 and submits; the scheduler may have been first.
    $this->travelTo(CarbonImmutable::parse('2026-10-23 10:01:20', 'UTC'));
    $this->artisan('attempts:expire')->assertSuccessful();
    $this->putJson(route('student.attempts.answers.update', [$attempt, 1]), ['answer' => ['value' => '8']])->assertStatus(409)->assertJson(['code' => 'attempt_expired']);
    $this->post(route('student.attempts.submit', $attempt))->assertRedirect(route('student.attempts.result', $attempt));

    $this->get(route('student.attempts.result', $attempt))
        ->assertInertia(fn (Assert $page) => $page->where('attempt.status', 'timed_out'));
    expect($attempt->fresh()?->questions()->firstOrFail()->answer()->firstOrFail()->answer)->toBe(['value' => '7']);
});
