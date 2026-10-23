<?php

declare(strict_types=1);

use App\Actions\Tests\SyncTestQuestions;
use App\Actions\Tests\UpdateTest;
use App\Domain\Questions\Definitions\ShortId;
use App\Domain\Tests\TestSettings;
use App\Enums\TestStatus;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use App\Support\AppTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestPayloads;

function lockTeacher(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

/**
 * A draft owned by $teacher with $count valid questions.
 *
 * @param  array<string, mixed>  $attributes
 */
function readyTest(User $teacher, int $count = 2, array $attributes = []): Test
{
    $test = Test::factory()->for($teacher, 'owner')->create(['shuffle_questions' => false, 'shuffle_options' => false, ...$attributes]);

    foreach (Question::factory()->count($count)->for($teacher, 'owner')->singleChoice()->create() as $index => $question) {
        $test->questions()->attach($question, ['position' => $index + 1]);
    }

    return $test;
}

/**
 * A published, locked test with 2 questions and known settings.
 */
function lockedTest(User $teacher): Test
{
    $test = readyTest($teacher, 2, [
        'status' => TestStatus::Published,
        'published_at' => now(),
        'locked_at' => now(),
        'time_limit_minutes' => 30,
        'max_attempts' => 2,
        'pass_percent' => 60,
        'opens_at' => now()->subDay()->startOfMinute(),
        'closes_at' => now()->addDays(3)->startOfMinute(),
        'results_visibility' => 'after_close',
        'show_correct_answers' => true,
        'issues_certificate' => true,
    ]);

    return $test->fresh() ?? $test;
}

describe('publishing', function (): void {
    it('publishes a valid draft', function (): void {
        $teacher = lockTeacher();
        $test = readyTest($teacher);

        $this->post(route('teacher.tests.publish', $test))->assertSessionHasNoErrors()->assertRedirect();

        expect($test->fresh()?->status)->toBe(TestStatus::Published)
            ->and($test->fresh()?->published_at)->not->toBeNull();
    });

    it('refuses to publish when a rule fails', function (Closure $prepare, string $error): void {
        $teacher = lockTeacher();
        $test = $prepare($teacher);

        $this->post(route('teacher.tests.publish', $test))->assertSessionHasErrors($error);

        expect($test->fresh()?->status)->toBe(TestStatus::Draft);
    })->with([
        'no questions' => [fn (User $teacher): Test => readyTest($teacher, 0), 'has_questions'],
        'pool larger than questions' => [fn (User $teacher): Test => readyTest($teacher, 2, ['selection_mode' => 'random_pool', 'random_question_count' => 3]), 'pool_size'],
        'closes in the past' => [fn (User $teacher): Test => readyTest($teacher, 1, ['closes_at' => now()->subHour()]), 'closes_in_future'],
        'invalid stored definition' => [function (User $teacher): Test {
            $test = readyTest($teacher, 1);
            // Bypass the editor: a definition its handler would reject (11 options).
            $question = $test->questions()->firstOrFail();
            $options = array_map(fn (int $i): array => ['id' => ShortId::generate(), 'text' => "Option {$i}"], range(1, 11));
            DB::table('questions')->where('id', $question->id)->update(['definition' => json_encode(['options' => $options, 'correct' => $options[0]['id']])]);

            return $test;
        }, 'valid_definitions'],
    ]);

    it('only warns when the window is shorter than the time limit or archived questions are attached', function (): void {
        $teacher = lockTeacher();
        $test = readyTest($teacher, 2, ['time_limit_minutes' => 120, 'closes_at' => now()->addHour()]);
        $test->questions()->firstOrFail()->delete();

        $checklist = $this->get(route('teacher.tests.edit', $test))->viewData('page')['props']['checklist'];
        $failed = collect($checklist)->where('ok', false)->pluck('severity', 'key')->all();

        expect($failed)->toBe(['no_archived' => 'warning', 'window_fits_time_limit' => 'warning']);

        $this->post(route('teacher.tests.publish', $test))->assertSessionHasNoErrors();
        expect($test->fresh()?->status)->toBe(TestStatus::Published);
    });

    it('unpublishes while not locked, never once locked', function (): void {
        $teacher = lockTeacher();
        $test = readyTest($teacher);
        $this->post(route('teacher.tests.publish', $test));

        $this->delete(route('teacher.tests.unpublish', $test))->assertSessionHasNoErrors();
        expect($test->fresh()?->status)->toBe(TestStatus::Draft);

        $this->post(route('teacher.tests.publish', $test));
        $test->forceFill(['locked_at' => now()])->save();

        $this->delete(route('teacher.tests.unpublish', $test))->assertSessionHasErrors('test');
        expect($test->fresh()?->status)->toBe(TestStatus::Published);
    });
});

describe('locked tests', function (): void {
    it('rejects every change to a locked field with 422', function (Closure $change, string $field): void {
        $teacher = lockTeacher();
        $test = lockedTest($teacher);
        $before = json_encode($test->getAttributes());
        $questionsBefore = $test->questions()->pluck('questions.id')->all();

        $this->putJson(route('teacher.tests.update', $test), TestPayloads::payload($test, $change($test, $teacher)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);

        expect(json_encode($test->fresh()?->getAttributes()))->toBe($before)
            ->and($test->questions()->pluck('questions.id')->all())->toBe($questionsBefore);
    })->with([
        'time limit' => [fn (): array => ['time_limit_minutes' => 45], 'time_limit_minutes'],
        'pass percent' => [fn (): array => ['pass_percent' => 50], 'pass_percent'],
        'shuffle questions' => [fn (Test $test): array => ['shuffle_questions' => ! $test->shuffle_questions], 'shuffle_questions'],
        'shuffle options' => [fn (Test $test): array => ['shuffle_options' => ! $test->shuffle_options], 'shuffle_options'],
        'opens at' => [fn (Test $test): array => ['opens_at' => AppTime::toLocalInput($test->opens_at?->subHour())], 'opens_at'],
        'fewer attempts' => [fn (): array => ['max_attempts' => 1], 'max_attempts'],
        'closes earlier' => [fn (Test $test): array => ['closes_at' => AppTime::toLocalInput($test->closes_at?->subHour())], 'closes_at'],
        'less revealing visibility' => [fn (): array => ['results_visibility' => 'manual'], 'results_visibility'],
        'hide correct answers' => [fn (): array => ['show_correct_answers' => false], 'show_correct_answers'],
        'certificate off' => [fn (): array => ['issues_certificate' => false], 'issues_certificate'],
        'selection mode' => [fn (): array => ['selection_mode' => 'random_pool', 'random_question_count' => 1], 'selection_mode'],
        'added question' => [fn (Test $test, User $teacher): array => ['questions' => TestPayloads::links([...$test->questions()->get()->all(), Question::factory()->for($teacher, 'owner')->create()])], 'questions'],
        'question order' => [fn (Test $test): array => ['questions' => TestPayloads::links(array_reverse($test->questions()->get()->all()))], 'questions'],
        'points override' => [fn (Test $test): array => ['questions' => TestPayloads::links($test->questions()->get()->all(), ['5'])], 'questions'],
    ]);

    it('accepts every allowed change', function (): void {
        $teacher = lockTeacher();
        $test = lockedTest($teacher);
        $test->forceFill(['results_visibility' => 'never', 'show_correct_answers' => false, 'issues_certificate' => false])->save();
        $test->refresh();
        $later = $test->closes_at?->addDays(2);

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, [
            'title' => 'Renamed while locked',
            'description' => 'Clarified wording',
            'max_attempts' => 5,
            'closes_at' => AppTime::toLocalInput($later),
            'results_visibility' => 'immediately',
            'show_correct_answers' => true,
            'issues_certificate' => true,
            'questions' => TestPayloads::links($test->questions()->get()->all()),
        ]))->assertSessionHasNoErrors();

        $test->refresh();
        expect($test->title)->toBe('Renamed while locked')
            ->and($test->max_attempts)->toBe(5)
            ->and($test->closes_at?->equalTo($later))->toBeTrue()
            ->and($test->results_visibility->value)->toBe('immediately')
            ->and($test->issues_certificate)->toBeTrue();
    });

    it('allows unlimited attempts and an open end', function (): void {
        $teacher = lockTeacher();
        $test = lockedTest($teacher);

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['max_attempts' => null, 'closes_at' => null]))
            ->assertSessionHasNoErrors();

        expect($test->fresh()?->max_attempts)->toBeNull()->and($test->fresh()?->closes_at)->toBeNull();
    });

    it('enforces the lock in the Actions too (not only the Form Request)', function (): void {
        $teacher = lockTeacher();
        $test = lockedTest($teacher);
        $settings = new TestSettings(
            $test->title, null, null, 99, $test->max_attempts, $test->pass_percent, $test->shuffle_questions, $test->shuffle_options,
            $test->selection_mode, null, $test->opens_at, $test->closes_at, $test->results_visibility, true, true,
        );

        expect(fn () => app(UpdateTest::class)->handle($test, $settings))->toThrow(ValidationException::class)
            ->and(fn () => app(SyncTestQuestions::class)->handle($test, []))->toThrow(ValidationException::class)
            ->and($test->fresh()?->time_limit_minutes)->toBe(30)
            ->and($test->questions()->count())->toBe(2);
    });

    it('can still be archived', function (): void {
        $teacher = lockTeacher();
        $test = lockedTest($teacher);

        $this->post(route('teacher.tests.archive', $test))->assertRedirect();

        expect($test->fresh()?->status)->toBe(TestStatus::Archived);
    });
});
