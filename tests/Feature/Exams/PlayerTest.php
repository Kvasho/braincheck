<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Definitions;
use App\Enums\QuestionType;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use App\Models\Question;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ExamFixtures;
use Tests\Support\PayloadLeakScanner;

function startedAttempt(User $student, array $types = [QuestionType::SingleChoice], array $attributes = []): Attempt
{
    $test = ExamFixtures::assignedTest($student, $types, $attributes);
    test()->actingAs($student)->post(route('student.exams.attempts.store', $test->ulid));

    return Attempt::query()->where('user_id', $student->id)->latest('id')->firstOrFail();
}

/**
 * The whole Inertia page object of the player.
 *
 * @return array<string, mixed>
 */
function playerPage(Attempt $attempt): array
{
    return test()->get(route('student.attempts.show', $attempt))->assertOk()->viewData('page');
}

/**
 * The page without the shared UI dictionary (`translations`: English UI keys
 * such as "Correct answer" are labels, not attempt data). Everything else,
 * shared props included, is scanned.
 *
 * @param  array<string, mixed>  $page
 * @return array<string, mixed>
 */
function withoutUiDictionary(array $page): array
{
    unset($page['props']['translations']);

    return $page;
}

describe('leaks (critical)', function (): void {
    it('never puts correct-answer data anywhere in the player page', function (QuestionType $type): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student, [$type], ['shuffle_options' => true]);
        $page = withoutUiDictionary(playerPage($attempt));

        expect(PayloadLeakScanner::findings($page))->toBe([]);

        $json = json_encode($page);
        foreach (['"correct', 'correct_order', '"pairs"', 'is_correct', 'reference_answer', 'tolerance', 'snapshot', 'definition', 'explanation', 'question_ulid', 'image_path'] as $forbidden) {
            expect($json)->not->toContain($forbidden);
        }
    })->with(QuestionType::cases());

    it('does not mark the correct option of choice questions in any way', function (QuestionType $type): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student, [$type]);
        $row = $attempt->questions()->firstOrFail();
        $definition = $row->snapshot['definition'];
        $correct = (array) ($type === QuestionType::SingleChoice ? [$definition['correct']] : $definition['correct']);

        $player = playerPage($attempt)['props']['attempt']['questions'][0]['player'];

        foreach ($player['options'] as $option) {
            expect(array_keys($option))->toBe(['id', 'text']);
        }

        // The correct ID appears only as an ordinary option, never as a value elsewhere.
        $ids = array_column($player['options'], 'id');
        expect(array_diff($correct, $ids))->toBe([])
            ->and(PayloadLeakScanner::findings(['questions' => playerPage($attempt)['props']['attempt']['questions']]))->toBe([]);
    })->with([QuestionType::SingleChoice, QuestionType::MultipleChoice]);

    it('sends only the documented props', function (): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student);

        $props = playerPage($attempt)['props']['attempt'];

        expect(array_keys($props))->toBe(['ulid', 'test', 'deadline_at', 'server_now', 'question_count', 'questions', 'saved'])
            ->and(array_keys($props['questions'][0]))->toBe(['position', 'type', 'points', 'body', 'image_url', 'player'])
            ->and(array_keys($props['saved'][0]))->toBe(['position', 'value', 'flagged', 'answered'])
            ->and($props['test'])->toBe(['title' => $attempt->test->title]);
    });
});

describe('access', function (): void {
    it('is 404 for another student, a preview and an unknown attempt', function (): void {
        $owner = ExamFixtures::student();
        $attempt = startedAttempt($owner);
        $preview = Attempt::factory()->for($attempt->test)->for($owner)->create(['is_preview' => true, 'attempt_number' => 7, 'status' => 'submitted', 'submitted_at' => now()]);

        $this->actingAs(ExamFixtures::student())->get(route('student.attempts.show', $attempt))->assertNotFound();
        $this->actingAs($owner)->get(route('student.attempts.show', $preview))->assertNotFound();
        $this->actingAs($owner)->get('/student/attempts/01JZZZZZZZZZZZZZZZZZZZZZZZ')->assertNotFound();
    });

    it('sends a finished attempt to its result page', function (): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student);
        $this->post(route('student.attempts.submit', $attempt));

        $this->get(route('student.attempts.show', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
        $this->get(route('student.attempts.result', $attempt))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('student/attempts/result')->where('attempt.status', 'submitted'));
    });

    it('finishes an expired attempt lazily and redirects', function (): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student, attributes: ['time_limit_minutes' => 5]);
        $this->travel(6)->minutes();

        $this->get(route('student.attempts.show', $attempt))->assertRedirect(route('student.attempts.result', $attempt));
        expect($attempt->fresh()?->status->value)->toBe('timed_out');
    });
});

describe('snapshots', function (): void {
    it('keeps the player payload when the question is edited after the start', function (): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student);
        $before = playerPage($attempt)['props']['attempt']['questions'];

        $question = Question::query()->firstOrFail();
        $question->forceFill([
            'definition' => Definitions::fromArray(QuestionType::SingleChoice, [
                'options' => [['id' => 'AAAAAAAA', 'text' => 'Edited A'], ['id' => 'BBBBBBBB', 'text' => 'Edited B']],
                'correct' => 'AAAAAAAA',
            ]),
        ])->save();

        expect(playerPage($attempt)['props']['attempt']['questions'])->toBe($before);
    });

    it('keeps the shuffled option order stable across reloads', function (): void {
        $student = ExamFixtures::student();
        $attempt = startedAttempt($student, [QuestionType::SingleChoice, QuestionType::Ordering, QuestionType::Matching], ['shuffle_options' => true]);

        $first = playerPage($attempt)['props']['attempt']['questions'];

        foreach (range(1, 3) as $reload) {
            expect(playerPage($attempt)['props']['attempt']['questions'])->toBe($first);
        }

        expect(AttemptQuestion::query()->where('attempt_id', $attempt->id)->count())->toBe(3);
    });
});

it('serves question images by position, only to the owner', function (): void {
    $student = ExamFixtures::student();
    $attempt = startedAttempt($student);

    // No image on this question: 404 for the owner too.
    $this->get(route('student.attempts.images.show', [$attempt, 1]))->assertNotFound();
    $this->actingAs(ExamFixtures::student())->get(route('student.attempts.images.show', [$attempt, 1]))->assertNotFound();
});
