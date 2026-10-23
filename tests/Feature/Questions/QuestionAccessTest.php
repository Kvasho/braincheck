<?php

declare(strict_types=1);

use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function teacherA(): User
{
    return User::factory()->teacher()->withTwoFactor()->create(['name' => 'Teacher A']);
}

function teacherB(): User
{
    return User::factory()->teacher()->withTwoFactor()->create(['name' => 'Teacher B']);
}

describe('IDOR: teacher B and teacher A\'s question', function (): void {
    it('can not touch it through any route', function (string $method, string $route, array $data): void {
        $question = Question::factory()->for(teacherA(), 'owner')->singleChoice()->create();
        $before = json_encode($question->fresh()?->getAttributes());

        $response = $this->actingAs(teacherB())->call($method, route($route, $question), $data);

        expect($response->status())->toBeIn([403, 404])
            ->and(json_encode($question->fresh()?->getAttributes()))->toBe($before)
            ->and(Question::query()->count())->toBe(1);
    })->with([
        'edit' => ['GET', 'teacher.questions.edit', []],
        'update' => ['PUT', 'teacher.questions.update', ['type' => 'single_choice']],
        'archive' => ['DELETE', 'teacher.questions.destroy', []],
        'duplicate' => ['POST', 'teacher.questions.duplicate', []],
        'preview' => ['GET', 'teacher.questions.preview', []],
        'preview grade' => ['POST', 'teacher.questions.preview.grade', ['answer' => null]],
        'image' => ['GET', 'question-images.show', []],
    ]);

    it('can not restore it', function (): void {
        $question = Question::factory()->for(teacherA(), 'owner')->create();
        $question->delete();

        $this->actingAs(teacherB())->post(route('teacher.questions.restore', $question))->assertForbidden();

        expect($question->fresh()?->trashed())->toBeTrue();
    });

    it('does not see it in the bank', function (): void {
        Question::factory()->for(teacherA(), 'owner')->create();

        $props = $this->actingAs(teacherB())->get(route('teacher.questions.index'))->viewData('page')['props'];

        expect($props['questions']['data'])->toBe([]);
    });
});

describe('bulk actions', function (): void {
    it('tags, untags and archives the teacher\'s own questions', function (): void {
        $teacher = teacherA();
        $questions = Question::factory()->count(3)->for($teacher, 'owner')->create();
        $tags = Tag::factory()->count(2)->create();
        $ulids = $questions->pluck('ulid')->all();

        $this->actingAs($teacher)->post(route('teacher.questions.bulk'), ['action' => 'tag', 'questions' => $ulids, 'tags' => $tags->pluck('ulid')->all()])
            ->assertSessionHasNoErrors();
        expect(DB::table('question_tag')->count())->toBe(6);

        // Adding again creates no duplicates.
        $this->post(route('teacher.questions.bulk'), ['action' => 'tag', 'questions' => $ulids, 'tags' => [$tags[0]->ulid]]);
        expect(DB::table('question_tag')->count())->toBe(6);

        $this->post(route('teacher.questions.bulk'), ['action' => 'untag', 'questions' => [$ulids[0]], 'tags' => [$tags[0]->ulid]]);
        expect(DB::table('question_tag')->count())->toBe(5);

        $this->post(route('teacher.questions.bulk'), ['action' => 'archive', 'questions' => [$ulids[1], $ulids[2]]]);
        expect(Question::query()->count())->toBe(1)->and(Question::onlyTrashed()->count())->toBe(2);
    });

    it('rejects the whole request when one ULID is foreign, processing nothing', function (string $action): void {
        $teacher = teacherA();
        $own = Question::factory()->count(2)->for($teacher, 'owner')->create();
        $foreign = Question::factory()->for(teacherB(), 'owner')->create();
        $tag = Tag::factory()->create();

        $this->actingAs($teacher)->post(route('teacher.questions.bulk'), [
            'action' => $action,
            'questions' => [...$own->pluck('ulid')->all(), $foreign->ulid],
            'tags' => [$tag->ulid],
        ])->assertForbidden();

        expect(DB::table('question_tag')->count())->toBe(0)
            ->and(Question::onlyTrashed()->count())->toBe(0);
    })->with(['tag', 'archive']);

    it('rejects unknown ULIDs and archived questions as not owned', function (): void {
        $teacher = teacherA();
        $own = Question::factory()->for($teacher, 'owner')->create();
        $archived = Question::factory()->for($teacher, 'owner')->create();
        $archived->delete();

        $this->actingAs($teacher)->post(route('teacher.questions.bulk'), ['action' => 'archive', 'questions' => [$own->ulid, (string) str()->ulid()]])->assertForbidden();
        $this->post(route('teacher.questions.bulk'), ['action' => 'archive', 'questions' => [$own->ulid, $archived->ulid]])->assertForbidden();

        expect($own->fresh()?->trashed())->toBeFalse();
    });

    it('validates the payload', function (array $payload, string $field): void {
        $teacher = teacherA();
        $own = Question::factory()->for($teacher, 'owner')->create();

        $this->actingAs($teacher)->post(route('teacher.questions.bulk'), [...$payload, 'questions' => $payload['questions'] ?? [$own->ulid]])
            ->assertSessionHasErrors($field);
    })->with([
        'unknown action' => [['action' => 'delete'], 'action'],
        'tag without tags' => [['action' => 'tag'], 'tags'],
        'too many questions' => [['action' => 'archive', 'questions' => array_fill(0, 101, 'x')], 'questions'],
        'empty questions' => [['action' => 'archive', 'questions' => []], 'questions'],
    ]);
});

describe('roles', function (): void {
    it('keeps students and admins out of the teacher routes', function (string $role): void {
        $user = User::factory()->{$role}()->withTwoFactor()->create();
        $question = Question::factory()->create();

        $this->actingAs($user)->get(route('teacher.questions.index'))->assertForbidden();
        $this->actingAs($user)->get(route('teacher.questions.edit', $question))->assertForbidden();
        $this->actingAs($user)->post(route('teacher.questions.store'), [])->assertForbidden();
    })->with(['student', 'admin']);

    it('lets admins browse and preview any question, read-only', function (): void {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $question = Question::factory()->singleChoice()->create();
        $question->delete();

        $this->actingAs($admin)->get(route('admin.questions.index', ['archived' => 'with']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('questions.data', 1)->where('questions.data.0.owner.ulid', $question->owner->ulid));

        $this->get(route('admin.questions.show', $question))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('review.correct')->has('preview.player.options'));
    });
});
