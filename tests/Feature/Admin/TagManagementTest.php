<?php

declare(strict_types=1);

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function tagAdmin(): User
{
    $admin = User::factory()->admin()->withTwoFactor()->create();
    test()->actingAs($admin);

    return $admin;
}

function tagNamed(string $name): Tag
{
    return Tag::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString()]);
}

/**
 * @return list<int>
 */
function questionIdsTagged(Tag $tag): array
{
    return DB::table('question_tag')->where('tag_id', $tag->id)->orderBy('question_id')->pluck('question_id')->map(fn ($id): int => (int) $id)->all();
}

describe('creating and editing', function (): void {
    it('creates a tag with a generated slug and audits it', function (): void {
        $admin = tagAdmin();

        $this->post(route('admin.tags.store'), ['name' => '  Linear algebra  '])->assertSessionHasNoErrors();

        $tag = Tag::query()->sole();
        expect($tag->name)->toBe('Linear algebra')
            ->and($tag->slug)->toBe('linear-algebra');

        $log = AuditLog::query()->where('action', AuditAction::TagCreated->value)->sole();
        expect($log->actor_id)->toBe($admin->id)->and($log->subject_id)->toBe($tag->id);
    });

    it('requires a unique name and slug', function (): void {
        tagAdmin();
        $existing = tagNamed('Algebra');
        $other = tagNamed('Geometry');

        $this->post(route('admin.tags.store'), ['name' => 'Algebra'])->assertSessionHasErrors('name');
        $this->post(route('admin.tags.store'), ['name' => ' Algebra '])->assertSessionHasErrors('name');
        $this->post(route('admin.tags.store'), ['name' => 'Something', 'slug' => 'algebra'])->assertSessionHasErrors('slug');
        $this->put(route('admin.tags.update', $other), ['name' => 'Algebra'])->assertSessionHasErrors('name');
        $this->put(route('admin.tags.update', $existing), ['name' => 'Algebra', 'slug' => 'algebra'])->assertSessionHasNoErrors();

        expect(Tag::query()->count())->toBe(2);
    });

    it('validates the slug format', function (): void {
        tagAdmin();

        $this->post(route('admin.tags.store'), ['name' => 'Algebra', 'slug' => 'Not A Slug'])->assertSessionHasErrors('slug');
    });

    it('updates a tag and audits the change', function (): void {
        tagAdmin();
        $tag = tagNamed('Algebra');

        $this->put(route('admin.tags.update', $tag), ['name' => 'Algebra I', 'slug' => 'algebra-1'])->assertSessionHasNoErrors();

        expect($tag->fresh()?->slug)->toBe('algebra-1');
        expect(AuditLog::query()->where('action', AuditAction::TagUpdated->value)->sole()->properties)->toBe([
            'from' => ['name' => 'Algebra', 'slug' => 'algebra'],
            'to' => ['name' => 'Algebra I', 'slug' => 'algebra-1'],
        ]);
    });
});

describe('listing', function (): void {
    it('shows usage counts and searches', function (): void {
        tagAdmin();
        $algebra = tagNamed('Algebra');
        tagNamed('Geometry');
        Question::factory()->count(3)->create()->each(fn (Question $question) => $question->tags()->attach($algebra));

        $this->get(route('admin.tags.index', ['search' => 'alg']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/tags/index')
                ->has('tags.data', 1)
                ->where('tags.data.0.name', 'Algebra')
                ->where('tags.data.0.questions_count', 3)
                ->missing('tags.data.0.id'));
    });

    it('sorts by usage', function (): void {
        tagAdmin();
        $popular = tagNamed('Popular');
        tagNamed('Unused');
        Question::factory()->count(2)->create()->each(fn (Question $question) => $question->tags()->attach($popular));

        $this->get(route('admin.tags.index', ['sort' => 'questions_count', 'direction' => 'desc']))
            ->assertInertia(fn (Assert $page) => $page->where('tags.data.0.name', 'Popular'));
    });
});

describe('merging', function (): void {
    it('moves every link to the target without duplicates and removes the source', function (): void {
        $admin = tagAdmin();
        $source = tagNamed('Algebra');
        $target = tagNamed('Algebra (old)');
        [$onlySource, $both, $onlyTarget] = Question::factory()->count(3)->create()->all();
        $archived = Question::factory()->create();
        $onlySource->tags()->attach($source);
        $both->tags()->attach([$source->id, $target->id]);
        $onlyTarget->tags()->attach($target);
        $archived->tags()->attach($source);
        $archived->delete();

        $this->post(route('admin.tags.merge', $source), ['target' => $target->ulid])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(Tag::query()->whereKey($source->id)->exists())->toBeFalse()
            ->and(questionIdsTagged($target))->toBe(collect([$onlySource->id, $both->id, $onlyTarget->id, $archived->id])->sort()->values()->all())
            ->and(DB::table('question_tag')->where('tag_id', $source->id)->count())->toBe(0)
            ->and(DB::table('question_tag')->where('question_id', $both->id)->count())->toBe(1);

        $log = AuditLog::query()->where('action', AuditAction::TagMerged->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->subject_id)->toBe($target->id)
            ->and($log->properties)->toMatchArray([
                'questions_moved' => 2,
                'questions_already_tagged' => 1,
            ])
            ->and($log->properties['source']['name'])->toBe('Algebra');
    });

    it('rejects merging a tag into itself or into an unknown tag', function (): void {
        tagAdmin();
        $tag = tagNamed('Algebra');

        $this->post(route('admin.tags.merge', $tag), ['target' => $tag->ulid])->assertSessionHasErrors('target');
        $this->post(route('admin.tags.merge', $tag), ['target' => (string) str()->ulid()])->assertSessionHasErrors('target');
        $this->post(route('admin.tags.merge', $tag), ['target' => '1'])->assertSessionHasErrors('target');

        expect(Tag::query()->whereKey($tag->id)->exists())->toBeTrue()
            ->and(AuditLog::query()->where('action', AuditAction::TagMerged->value)->count())->toBe(0);
    });
});

describe('deleting', function (): void {
    it('deletes a tag, its links, and audits the usage', function (): void {
        $admin = tagAdmin();
        $tag = tagNamed('Algebra');
        $question = Question::factory()->create();
        $question->tags()->attach($tag);

        $this->delete(route('admin.tags.destroy', $tag))->assertSessionHasNoErrors();

        expect(Tag::query()->count())->toBe(0)
            ->and($question->fresh())->not->toBeNull()
            ->and(DB::table('question_tag')->count())->toBe(0);

        $log = AuditLog::query()->where('action', AuditAction::TagDeleted->value)->sole();
        expect($log->actor_id)->toBe($admin->id)
            ->and($log->properties)->toBe(['name' => 'Algebra', 'slug' => 'algebra', 'questions_untagged' => 1]);
    });
});
