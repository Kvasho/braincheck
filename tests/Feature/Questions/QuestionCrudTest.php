<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Shared\RichTextDocument;
use App\Enums\QuestionType;
use App\Enums\TestStatus;
use App\Models\Attempt;
use App\Models\Category;
use App\Models\Question;
use App\Models\Tag;
use App\Models\Test;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function questionTeacher(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

/**
 * @return array<string, mixed>
 */
function bodyDoc(string $text = 'What is the capital of Georgia?'): array
{
    return RichTextDocument::fromPlainText($text)->toArray();
}

/**
 * A valid editor input per type (the shape the frontend modules send).
 *
 * @return array<string, mixed>
 */
function definitionInput(QuestionType $type): array
{
    return match ($type) {
        QuestionType::SingleChoice => ['options' => [['text' => 'Tbilisi', 'correct' => true], ['text' => 'Batumi', 'correct' => false]]],
        QuestionType::MultipleChoice => ['options' => [['text' => '2', 'correct' => true], ['text' => '3', 'correct' => true], ['text' => '4', 'correct' => false]], 'scoring' => 'partial'],
        QuestionType::TrueFalse => ['correct' => false],
        QuestionType::Numeric => ['correct' => '9.81', 'tolerance' => '0.05', 'unit' => 'm/s²'],
        QuestionType::Text => ['max_length' => 500, 'reference_answer' => 'Photosynthesis'],
        QuestionType::Ordering => ['items' => [['text' => 'One'], ['text' => 'Two'], ['text' => 'Three']], 'scoring' => 'partial'],
        QuestionType::Matching => ['pairs' => [['left' => ['text' => 'Georgia'], 'right' => ['text' => 'Tbilisi']], ['left' => ['text' => 'France'], 'right' => ['text' => 'Paris']]], 'distractors' => [['text' => 'Rome']]],
    };
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function questionPayload(QuestionType $type, array $overrides = []): array
{
    return [
        'type' => $type->value,
        'body' => bodyDoc(),
        'explanation' => null,
        'category' => null,
        'tags' => [],
        'difficulty' => 'medium',
        'points' => '2.00',
        'definition' => definitionInput($type),
        ...$overrides,
    ];
}

function ownedSingleChoice(User $owner): Question
{
    $question = Question::factory()->for($owner, 'owner')->singleChoice()->create();

    return $question->fresh() ?? $question;
}

describe('creating', function (): void {
    it('creates every type through the HTTP layer', function (QuestionType $type): void {
        $teacher = questionTeacher();

        $this->post(route('teacher.questions.store'), questionPayload($type))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $question = Question::query()->sole();
        expect($question->owner_id)->toBe($teacher->id)
            ->and($question->type)->toBe($type)
            ->and($question->version)->toBe(1)
            ->and($question->points)->toBe('2.00')
            ->and($question->body_text)->toBe('What is the capital of Georgia?');
    })->with(QuestionType::cases());

    it('accepts multipart requests with JSON-encoded structured fields', function (): void {
        questionTeacher();
        $payload = questionPayload(QuestionType::SingleChoice);

        $this->post(route('teacher.questions.store'), [
            ...$payload,
            'body' => json_encode($payload['body']),
            'definition' => json_encode($payload['definition']),
            'tags' => json_encode([]),
            'explanation' => 'null',
        ])->assertSessionHasNoErrors();

        expect(Question::query()->sole()->definition)->toBeInstanceOf(SingleChoiceDefinition::class);
    });

    it('stores category and tags and ignores ownership fields from the request', function (): void {
        $teacher = questionTeacher();
        $other = User::factory()->teacher()->create();
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        $this->post(route('teacher.questions.store'), questionPayload(QuestionType::TrueFalse, [
            'category' => $category->ulid,
            'tags' => $tags->pluck('ulid')->all(),
            'owner_id' => $other->id,
            'version' => 99,
        ]))->assertSessionHasNoErrors();

        $question = Question::query()->with('tags')->sole();
        expect($question->owner_id)->toBe($teacher->id)
            ->and($question->version)->toBe(1)
            ->and($question->category_id)->toBe($category->id)
            ->and($question->tags->pluck('id')->sort()->values()->all())->toBe($tags->pluck('id')->sort()->values()->all());
    });

    it('validates every type\'s definition through the HTTP layer', function (QuestionType $type, array $definition, string $field): void {
        questionTeacher();

        $this->post(route('teacher.questions.store'), questionPayload($type, ['definition' => $definition]))
            ->assertSessionHasErrors($field);

        expect(Question::query()->count())->toBe(0);
    })->with([
        'single: two correct' => [QuestionType::SingleChoice, ['options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => true]]], 'definition.options'],
        'single: one option' => [QuestionType::SingleChoice, ['options' => [['text' => 'A', 'correct' => true]]], 'definition.options'],
        'multiple: none correct' => [QuestionType::MultipleChoice, ['options' => [['text' => 'A', 'correct' => false], ['text' => 'B', 'correct' => false]], 'scoring' => 'partial'], 'definition.options'],
        'multiple: bad scoring' => [QuestionType::MultipleChoice, ['options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => false]], 'scoring' => 'x'], 'definition.scoring'],
        'true/false: not boolean' => [QuestionType::TrueFalse, ['correct' => 'maybe'], 'definition.correct'],
        'numeric: float' => [QuestionType::Numeric, ['correct' => 9.81, 'tolerance' => '0'], 'definition.correct'],
        'numeric: negative tolerance' => [QuestionType::Numeric, ['correct' => '1', 'tolerance' => '-1'], 'definition.tolerance'],
        'text: max_length too big' => [QuestionType::Text, ['max_length' => 6000], 'definition.max_length'],
        'ordering: one item' => [QuestionType::Ordering, ['items' => [['text' => 'A']], 'scoring' => 'partial'], 'definition.items'],
        'matching: six distractors' => [QuestionType::Matching, ['pairs' => [['left' => ['text' => 'A'], 'right' => ['text' => 'B']], ['left' => ['text' => 'C'], 'right' => ['text' => 'D']]], 'distractors' => array_fill(0, 6, ['text' => 'x'])], 'definition.distractors'],
        'missing definition' => [QuestionType::TrueFalse, [], 'definition'],
    ]);

    it('validates the common fields', function (array $overrides, string $field): void {
        questionTeacher();

        $this->post(route('teacher.questions.store'), questionPayload(QuestionType::TrueFalse, $overrides))
            ->assertSessionHasErrors($field);
    })->with([
        'unknown type' => [['type' => 'essay'], 'type'],
        'empty body' => [['body' => ['type' => 'doc', 'content' => []]], 'body'],
        'html body' => [['body' => '<p>Hello</p>'], 'body'],
        'script node in body' => [['body' => ['type' => 'doc', 'content' => [['type' => 'script', 'content' => [['type' => 'text', 'text' => 'alert(1)']]]]]], 'body'],
        'link mark in body' => [['body' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]]]]]]], 'body'],
        'zero points' => [['points' => '0'], 'points'],
        'three decimals' => [['points' => '1.005'], 'points'],
        'float points' => [['points' => 1.5], 'points'],
        'unknown difficulty' => [['difficulty' => 'brutal'], 'difficulty'],
        'unknown category' => [['category' => (string) str()->ulid()], 'category'],
        'unknown tag' => [['tags' => [(string) str()->ulid()]], 'tags.0'],
    ]);
});

describe('editing', function (): void {
    it('shows the owner the editor input, including the answer', function (): void {
        $teacher = questionTeacher();
        $question = ownedSingleChoice($teacher);

        $this->get(route('teacher.questions.edit', $question))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('teacher/questions/form')
                ->where('question.ulid', $question->ulid)
                ->has('question.definition.options')
                ->where('liveTests', 0)
                ->missing('question.id'));
    });

    it('increments the version only when body, definition or points change', function (): void {
        $teacher = questionTeacher();
        $this->post(route('teacher.questions.store'), questionPayload(QuestionType::TrueFalse));
        $question = Question::query()->sole();
        $payload = questionPayload(QuestionType::TrueFalse);

        $update = fn (array $overrides) => $this->put(route('teacher.questions.update', $question), [...$payload, ...$overrides])->assertSessionHasNoErrors();

        $update(['difficulty' => 'hard', 'explanation' => bodyDoc('Because.'), 'tags' => [Tag::factory()->create()->ulid]]);
        expect($question->fresh()?->version)->toBe(1);

        $update(['body' => bodyDoc('Is the sky green?')]);
        expect($question->fresh()?->version)->toBe(2);

        $update(['body' => bodyDoc('Is the sky green?'), 'definition' => ['correct' => true]]);
        expect($question->fresh()?->version)->toBe(3);

        $update(['body' => bodyDoc('Is the sky green?'), 'definition' => ['correct' => true], 'points' => '5']);
        expect($question->fresh()?->version)->toBe(4);

        $update(['body' => bodyDoc('Is the sky green?'), 'definition' => ['correct' => true], 'points' => '5.00']);
        expect($question->fresh()?->version)->toBe(4);
    });

    it('keeps option ids stable when editing', function (): void {
        questionTeacher();
        $this->post(route('teacher.questions.store'), questionPayload(QuestionType::SingleChoice));
        $question = Question::query()->sole();
        $ids = array_map(fn (Item $option): string => $option->id, $question->definition->options);

        $input = $this->get(route('teacher.questions.edit', $question))->viewData('page')['props']['question']['definition'];
        $input['options'][1]['text'] = 'Kutaisi';

        $this->put(route('teacher.questions.update', $question), questionPayload(QuestionType::SingleChoice, ['definition' => $input]))
            ->assertSessionHasNoErrors();

        $definition = $question->fresh()?->definition;
        expect(array_map(fn (Item $option): string => $option->id, $definition->options))->toBe($ids)
            ->and($definition->options[1]->text)->toBe('Kutaisi');
    });

    it('never changes the type', function (): void {
        $teacher = questionTeacher();
        $question = ownedSingleChoice($teacher);

        $this->put(route('teacher.questions.update', $question), questionPayload(QuestionType::TrueFalse, ['type' => 'true_false']))
            ->assertSessionHasErrors('definition.options');

        expect($question->fresh()?->type)->toBe(QuestionType::SingleChoice);
    });

    it('warns when a published test with attempts uses the question', function (): void {
        $teacher = questionTeacher();
        $question = ownedSingleChoice($teacher);
        $test = Test::factory()->for($teacher, 'owner')->create(['status' => TestStatus::Published]);
        $test->questions()->attach($question, ['position' => 1]);
        Attempt::factory()->for($test)->create();

        $this->get(route('teacher.questions.edit', $question))
            ->assertInertia(fn (Assert $page) => $page->where('liveTests', 1));
    });
});

describe('duplicate, archive, restore', function (): void {
    it('duplicates with fresh option ids and version 1', function (): void {
        $teacher = questionTeacher();
        $tag = Tag::factory()->create();
        $this->post(route('teacher.questions.store'), questionPayload(QuestionType::Matching, ['tags' => [$tag->ulid]]));
        $original = Question::query()->sole();
        $this->put(route('teacher.questions.update', $original), questionPayload(QuestionType::Matching, ['points' => '3', 'tags' => [$tag->ulid]]));

        $this->post(route('teacher.questions.duplicate', $original))->assertRedirect();

        $copy = Question::query()->whereKeyNot($original->id)->with('tags')->sole();
        $ids = fn (Question $q): array => [...array_map(fn ($i) => $i->id, $q->definition->left), ...array_map(fn ($i) => $i->id, $q->definition->right)];

        expect($copy->owner_id)->toBe($teacher->id)
            ->and($copy->version)->toBe(1)
            ->and($copy->points)->toBe('3.00')
            ->and($copy->body->toArray())->toBe($original->fresh()?->body->toArray())
            ->and($copy->tags)->toHaveCount(1)
            ->and(array_intersect($ids($copy), $ids($original)))->toBe([])
            ->and(count($copy->definition->pairs))->toBe(2);
    });

    it('archives and restores', function (): void {
        $teacher = questionTeacher();
        $question = ownedSingleChoice($teacher);

        $this->delete(route('teacher.questions.destroy', $question))->assertRedirect(route('teacher.questions.index'));
        expect($question->fresh()?->trashed())->toBeTrue();

        $this->get(route('teacher.questions.edit', $question))->assertNotFound();

        $this->post(route('teacher.questions.restore', $question))->assertRedirect();
        expect($question->fresh()?->trashed())->toBeFalse();
    });
});

describe('bank listing', function (): void {
    it('lists only the teacher\'s own questions, without definitions', function (): void {
        $teacher = questionTeacher();
        ownedSingleChoice($teacher);
        Question::factory()->count(2)->create();

        $response = $this->get(route('teacher.questions.index'))->assertOk();
        $props = $response->viewData('page')['props'];

        expect($props['questions']['data'])->toHaveCount(1)
            ->and(array_keys($props['questions']['data'][0]))->not->toContain('definition')
            ->and(json_encode($props['questions']))->not->toContain('"correct"');
    });

    it('searches, filters and sorts', function (): void {
        $teacher = questionTeacher();
        $category = Category::factory()->create();
        $child = Category::factory()->childOf($category)->create();
        $tag = Tag::factory()->create();

        $hard = Question::factory()->for($teacher, 'owner')->trueFalse()->create(['difficulty' => 'hard', 'body' => RichTextDocument::fromPlainText('Photosynthesis needs light')]);
        $easy = Question::factory()->for($teacher, 'owner')->singleChoice()->create(['difficulty' => 'easy', 'category_id' => $child->id]);
        $easy->tags()->attach($tag);
        $archived = Question::factory()->for($teacher, 'owner')->singleChoice()->create();
        $archived->delete();

        $ulids = fn (array $query): array => collect($this->get(route('teacher.questions.index', $query))->assertOk()->viewData('page')['props']['questions']['data'])->pluck('ulid')->all();

        expect($ulids(['search' => 'photosynth']))->toBe([$hard->ulid])
            ->and($ulids(['type' => 'single_choice']))->toBe([$easy->ulid])
            ->and($ulids(['difficulty' => 'hard']))->toBe([$hard->ulid])
            ->and($ulids(['category' => $category->ulid]))->toBe([$easy->ulid])
            ->and($ulids(['tags' => [$tag->ulid]]))->toBe([$easy->ulid])
            ->and($ulids(['archived' => 'only']))->toBe([$archived->ulid])
            ->and($ulids(['sort' => 'difficulty', 'direction' => 'asc']))->toBe([$easy->ulid, $hard->ulid])
            ->and($ulids(['sort' => 'difficulty', 'direction' => 'desc']))->toBe([$hard->ulid, $easy->ulid])
            ->and($ulids(['sort' => 'id; DROP TABLE questions']))->toHaveCount(2);
    });
});
