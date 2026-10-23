<?php

declare(strict_types=1);

use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\Test;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestPayloads;

function builderTeacher(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

describe('creating and listing', function (): void {
    it('creates a draft and opens the builder', function (): void {
        $teacher = builderTeacher();

        $this->post(route('teacher.tests.store'), ['title' => 'Algebra midterm', 'description' => 'Chapters 1–3'])
            ->assertRedirect();

        $test = Test::query()->sole();
        expect($test->owner_id)->toBe($teacher->id)
            ->and($test->status)->toBe(TestStatus::Draft)
            ->and($test->pass_percent)->toBe(60);

        $this->get(route('teacher.tests.edit', $test))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('teacher/tests/builder')->where('test.ulid', $test->ulid)->has('checklist'));
    });

    it('validates the title and description', function (array $data, string $field): void {
        builderTeacher();

        $this->post(route('teacher.tests.store'), $data)->assertSessionHasErrors($field);
    })->with([
        'too short' => [['title' => 'ab'], 'title'],
        'too long' => [['title' => str_repeat('a', 151)], 'title'],
        'long description' => [['title' => 'Fine title', 'description' => str_repeat('a', 2001)], 'description'],
    ]);

    it('lists only own tests with counts that exclude previews', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->published()->create(['title' => 'Physics quiz']);
        $test->questions()->attach(Question::factory()->for($teacher, 'owner')->create(), ['position' => 1]);
        Attempt::factory()->for($test)->create();
        Attempt::factory()->for($test)->for($teacher)->create(['is_preview' => true]);
        Test::factory()->create(['title' => 'Someone else\'s']);

        $rows = $this->get(route('teacher.tests.index'))->assertOk()->viewData('page')['props']['tests']['data'];

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['questions_count'])->toBe(1)
            ->and($rows[0]['attempts_count'])->toBe(1);
    });

    it('filters by status and searches titles', function (): void {
        $teacher = builderTeacher();
        Test::factory()->for($teacher, 'owner')->create(['title' => 'Draft algebra']);
        Test::factory()->for($teacher, 'owner')->published()->create(['title' => 'Published biology']);

        $titles = fn (array $query): array => collect($this->get(route('teacher.tests.index', $query))->viewData('page')['props']['tests']['data'])->pluck('title')->all();

        expect($titles(['status' => 'published']))->toBe(['Published biology'])
            ->and($titles(['search' => 'algeb']))->toBe(['Draft algebra']);
    });
});

describe('saving the builder', function (): void {
    it('saves settings and the ordered question list with overrides', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();
        [$a, $b, $c] = Question::factory()->count(3)->for($teacher, 'owner')->create()->all();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, [
            'title' => 'Renamed',
            'time_limit_minutes' => 45,
            'max_attempts' => 3,
            'pass_percent' => 70,
            'questions' => TestPayloads::links([$c, $a, $b], [1 => '2.50']),
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $test->refresh();
        $rows = $test->questions()->get()->map(fn (Question $q): array => [$q->id, $q->pivot->position, $q->pivot->points_override])->all();

        expect($test->title)->toBe('Renamed')
            ->and($test->time_limit_minutes)->toBe(45)
            ->and($rows)->toBe([[$c->id, 1, null], [$a->id, 2, '2.50'], [$b->id, 3, null]]);
    });

    it('rejects the whole request when one question is foreign (403)', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();
        $own = Question::factory()->for($teacher, 'owner')->create();
        $foreign = Question::factory()->create();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['title' => 'Changed', 'questions' => TestPayloads::links([$own, $foreign])]))
            ->assertForbidden();

        expect($test->fresh()?->title)->not->toBe('Changed')
            ->and($test->questions()->count())->toBe(0);
    });

    it('does not add archived questions, but keeps them when already attached', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();
        $attached = Question::factory()->for($teacher, 'owner')->create();
        $test->questions()->attach($attached, ['position' => 1]);
        $attached->delete();
        $archived = Question::factory()->for($teacher, 'owner')->create();
        $archived->delete();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['questions' => TestPayloads::links([$attached, $archived])]))
            ->assertSessionHasErrors('questions');

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['questions' => TestPayloads::links([$attached])]))
            ->assertSessionHasNoErrors();

        expect($test->questions()->withTrashed()->count())->toBe(1);
    });

    it('validates the question list and settings', function (array $overrides, string $field): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();
        $question = Question::factory()->for($teacher, 'owner')->create();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['questions' => TestPayloads::links([$question]), ...$overrides]))
            ->assertSessionHasErrors($field);
    })->with([
        'time limit 601' => [['time_limit_minutes' => 601], 'time_limit_minutes'],
        'max attempts 21' => [['max_attempts' => 21], 'max_attempts'],
        'pass percent 101' => [['pass_percent' => 101], 'pass_percent'],
        'pool larger than questions' => [['selection_mode' => 'random_pool', 'random_question_count' => 2], 'random_question_count'],
        'pool without count' => [['selection_mode' => 'random_pool'], 'random_question_count'],
        'closes before opens' => [['opens_at' => '2030-01-02T10:00', 'closes_at' => '2030-01-01T10:00'], 'closes_at'],
        'bad date format' => [['opens_at' => '02.01.2030 10:00'], 'opens_at'],
        'unknown visibility' => [['results_visibility' => 'sometimes'], 'results_visibility'],
    ]);

    it('rejects a zero points override', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();
        $question = Question::factory()->for($teacher, 'owner')->create();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['questions' => TestPayloads::links([$question], ['0'])]))
            ->assertSessionHasErrors('questions.0.points_override');
    });

    it('rejects duplicate questions in the list', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();
        $question = Question::factory()->for($teacher, 'owner')->create();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['questions' => TestPayloads::links([$question, $question])]))
            ->assertSessionHasErrors('questions.0.ulid');
    });

    it('does not let another teacher open or change the test', function (): void {
        $test = Test::factory()->create();
        builderTeacher();

        $this->get(route('teacher.tests.edit', $test))->assertForbidden();
        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['title' => 'Hijacked']))->assertForbidden();
        $this->post(route('teacher.tests.publish', $test))->assertForbidden();
        $this->delete(route('teacher.tests.unpublish', $test))->assertForbidden();
        $this->post(route('teacher.tests.archive', $test))->assertForbidden();
        $this->post(route('teacher.tests.duplicate', $test))->assertForbidden();
        $this->post(route('teacher.tests.preview.store', $test))->assertForbidden();

        expect($test->fresh()?->title)->not->toBe('Hijacked');
    });

    it('makes archived tests read-only', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->archived()->create();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, ['title' => 'Revived']))->assertForbidden();
    });
});

describe('time zone', function (): void {
    it('stores Asia/Tbilisi input as UTC and shows it back, across a date boundary', function (): void {
        $this->travelTo(CarbonImmutable::parse('2026-10-31 20:30:00', 'UTC')); // 00:30 on 1 Nov in Tbilisi
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->create();

        $this->put(route('teacher.tests.update', $test), TestPayloads::payload($test, [
            'opens_at' => '2026-11-01T02:00',
            'closes_at' => '2026-11-01T03:30',
        ]))->assertSessionHasNoErrors();

        $test->refresh();
        expect($test->opens_at?->toDateTimeString())->toBe('2026-10-31 22:00:00')
            ->and($test->closes_at?->toDateTimeString())->toBe('2026-10-31 23:30:00');

        $this->get(route('teacher.tests.edit', $test))
            ->assertInertia(fn (Assert $page) => $page
                ->where('test.opens_at_local', '2026-11-01T02:00')
                ->where('test.closes_at_local', '2026-11-01T03:30')
                ->where('options.timezone', 'Asia/Tbilisi'));
    });
});

describe('duplicate and archive', function (): void {
    it('duplicates into an independent draft without schedule', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->published()->create([
            'title' => 'Final exam',
            'opens_at' => now()->addDay(),
            'closes_at' => now()->addDays(2),
            'time_limit_minutes' => 30,
            'locked_at' => now(),
        ]);
        $question = Question::factory()->for($teacher, 'owner')->create();
        $test->questions()->attach($question, ['position' => 1, 'points_override' => '3.00']);

        $this->post(route('teacher.tests.duplicate', $test))->assertRedirect();

        $copy = Test::query()->whereKeyNot($test->id)->sole();
        expect($copy->title)->toBe('Final exam (copy)')
            ->and($copy->status)->toBe(TestStatus::Draft)
            ->and($copy->opens_at)->toBeNull()
            ->and($copy->closes_at)->toBeNull()
            ->and($copy->locked_at)->toBeNull()
            ->and($copy->time_limit_minutes)->toBe(30)
            ->and($copy->questions()->first()?->pivot->points_override)->toBe('3.00');

        // Independent: editing the copy leaves the original alone.
        $this->put(route('teacher.tests.update', $copy), TestPayloads::payload($copy, ['questions' => []]))->assertSessionHasNoErrors();
        expect($test->questions()->count())->toBe(1);
    });

    it('archives a test', function (): void {
        $teacher = builderTeacher();
        $test = Test::factory()->for($teacher, 'owner')->published()->create();

        $this->post(route('teacher.tests.archive', $test))->assertRedirect(route('teacher.tests.index'));

        expect($test->fresh()?->status)->toBe(TestStatus::Archived)
            ->and(Test::query()->published()->count())->toBe(0);
    });
});
