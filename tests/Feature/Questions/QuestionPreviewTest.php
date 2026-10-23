<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Shared\RichTextDocument;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\User;
use Tests\Support\PayloadLeakScanner;

/**
 * A valid editor input per type (as the frontend modules send it).
 *
 * @return array<string, mixed>
 */
function previewDefinitionInput(QuestionType $type): array
{
    return match ($type) {
        QuestionType::SingleChoice => ['options' => [['text' => 'Tbilisi', 'correct' => true], ['text' => 'Batumi', 'correct' => false], ['text' => 'Kutaisi', 'correct' => false]]],
        QuestionType::MultipleChoice => ['options' => [['text' => '2', 'correct' => true], ['text' => '3', 'correct' => true], ['text' => '4', 'correct' => false]], 'scoring' => 'partial'],
        QuestionType::TrueFalse => ['correct' => false],
        QuestionType::Numeric => ['correct' => '9.81', 'tolerance' => '0.05', 'unit' => 'm/s²'],
        QuestionType::Text => ['max_length' => 500, 'reference_answer' => 'The secret model answer'],
        QuestionType::Ordering => ['items' => [['text' => 'First'], ['text' => 'Second'], ['text' => 'Third'], ['text' => 'Fourth']], 'scoring' => 'all_or_nothing'],
        QuestionType::Matching => ['pairs' => [['left' => ['text' => 'Georgia'], 'right' => ['text' => 'Tbilisi']], ['left' => ['text' => 'France'], 'right' => ['text' => 'Paris']]], 'distractors' => [['text' => 'Rome']]],
    };
}

/**
 * The correct answer, built only from what the teacher typed (not from the
 * stored definition): the editor input knows which texts are right.
 *
 * @param  array<string, mixed>  $player
 * @return array<string, mixed>
 */
function correctAnswerFor(QuestionType $type, array $player): array
{
    $idOf = fn (array $items, string $text): string => collect($items)->firstWhere('text', $text)['id'];

    return match ($type) {
        QuestionType::SingleChoice => ['selected' => $idOf($player['options'], 'Tbilisi')],
        QuestionType::MultipleChoice => ['selected' => [$idOf($player['options'], '2'), $idOf($player['options'], '3')]],
        QuestionType::TrueFalse => ['value' => false],
        QuestionType::Numeric => ['value' => '9,83'],
        QuestionType::Text => ['text' => 'Chlorophyll absorbs light.'],
        QuestionType::Ordering => ['order' => array_map(fn (string $text): string => $idOf($player['items'], $text), ['First', 'Second', 'Third', 'Fourth'])],
        QuestionType::Matching => ['pairs' => [
            ['left' => $idOf($player['left'], 'Georgia'), 'right' => $idOf($player['right'], 'Tbilisi')],
            ['left' => $idOf($player['left'], 'France'), 'right' => $idOf($player['right'], 'Paris')],
        ]],
    };
}

/**
 * @return list<string>
 */
function previewSecrets(QuestionDefinition $definition): array
{
    $data = $definition->toArray();

    return array_values(array_filter([
        is_string($data['reference_answer'] ?? null) ? $data['reference_answer'] : null,
        $definition->type() === QuestionType::Numeric ? (string) $data['correct'] : null,
    ]));
}

function previewTeacher(): User
{
    $teacher = User::factory()->teacher()->withTwoFactor()->create();
    test()->actingAs($teacher);

    return $teacher;
}

function createQuestionOverHttp(QuestionType $type): Question
{
    test()->post(route('teacher.questions.store'), [
        'type' => $type->value,
        'body' => RichTextDocument::fromPlainText("A {$type->value} question")->toArray(),
        'difficulty' => 'medium',
        'points' => '2.50',
        'definition' => previewDefinitionInput($type),
    ])->assertSessionHasNoErrors();

    return Question::query()->latest('id')->firstOrFail();
}

it('end to end: create, preview and answer correctly for a full score', function (QuestionType $type): void {
    previewTeacher();
    $question = createQuestionOverHttp($type);

    $player = $this->get(route('teacher.questions.preview', $question))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('teacher/questions/preview')->where('canGrade', true))
        ->viewData('page')['props']['question']['player'];

    $graded = $this->postJson(route('teacher.questions.preview.grade', $question), ['answer' => correctAnswerFor($type, $player)])
        ->assertOk()
        ->json();

    if ($type === QuestionType::Text) {
        expect($graded['result'])->toBe(['is_correct' => null, 'awarded_points' => '0.00', 'needs_manual_grading' => true]);
    } else {
        expect($graded['result'])->toBe(['is_correct' => true, 'awarded_points' => '2.50', 'needs_manual_grading' => false]);
    }
})->with(QuestionType::cases());

it('never leaks the answer in the preview page', function (QuestionType $type): void {
    previewTeacher();
    $question = createQuestionOverHttp($type);

    $response = $this->get(route('teacher.questions.preview', $question))->assertOk();
    $props = $response->viewData('page')['props']['question'];

    expect(PayloadLeakScanner::findings($props['player'], previewSecrets($question->definition)))->toBe([])
        ->and(array_keys($props))->toBe(['ulid', 'type', 'body', 'image_url', 'points', 'player'])
        ->and((string) $response->getContent())->not->toContain('The secret model answer');
})->with(QuestionType::cases());

it('accepts the answer as JSON text (the preview page sends it that way)', function (): void {
    previewTeacher();
    $question = createQuestionOverHttp(QuestionType::TrueFalse);

    $this->postJson(route('teacher.questions.preview.grade', $question), ['answer' => json_encode(['value' => false])])
        ->assertOk()
        ->assertJsonPath('result.is_correct', true);
});

it('validates preview answers like student answers', function (QuestionType $type, array $answer): void {
    previewTeacher();
    $question = createQuestionOverHttp($type);

    $this->postJson(route('teacher.questions.preview.grade', $question), ['answer' => $answer])->assertUnprocessable();
})->with([
    'single: unknown id' => [QuestionType::SingleChoice, ['selected' => 'ZZZZZZZZ']],
    'multiple: duplicates' => [QuestionType::MultipleChoice, ['selected' => ['AAAAAAAA', 'AAAAAAAA']]],
    'numeric: exponent' => [QuestionType::Numeric, ['value' => '1e3']],
    'ordering: partial order' => [QuestionType::Ordering, ['order' => []]],
    'matching: wrong structure' => [QuestionType::Matching, ['pairs' => 'Georgia=Tbilisi']],
    'text: too long' => [QuestionType::Text, ['text' => str_repeat('a', 501)]],
]);

it('lets only the owner grade a preview (admins may only look)', function (): void {
    $owner = previewTeacher();
    $question = createQuestionOverHttp(QuestionType::TrueFalse);
    $admin = User::factory()->admin()->withTwoFactor()->create();

    // Admins are kept out of the teacher area entirely; their read-only view is /admin/questions.
    $this->actingAs($admin)->postJson(route('teacher.questions.preview.grade', $question), ['answer' => ['value' => true]])->assertForbidden();
    $this->actingAs(User::factory()->teacher()->withTwoFactor()->create())->postJson(route('teacher.questions.preview.grade', $question), ['answer' => ['value' => true]])->assertForbidden();
    $this->actingAs($owner)->postJson(route('teacher.questions.preview.grade', $question), ['answer' => ['value' => true]])->assertOk();
});

it('rate limits preview grading', function (): void {
    previewTeacher();
    $question = createQuestionOverHttp(QuestionType::TrueFalse);

    foreach (range(1, 30) as $attempt) {
        $this->postJson(route('teacher.questions.preview.grade', $question), ['answer' => null])->assertOk();
    }

    $this->postJson(route('teacher.questions.preview.grade', $question), ['answer' => null])->assertStatus(429);
});
