<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Domain\Shared\Randomizers;
use App\Enums\QuestionType;
use App\Models\Question;
use Tests\Support\PayloadLeakScanner;
use Tests\Support\QuestionHandlers;

/**
 * One valid editor input per type.
 *
 * @return array<string, mixed>
 */
function editorInput(QuestionType $type): array
{
    return match ($type) {
        QuestionType::SingleChoice => ['options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => false], ['text' => 'C', 'correct' => false]]],
        QuestionType::MultipleChoice => ['options' => [['text' => 'A', 'correct' => true], ['text' => 'B', 'correct' => true], ['text' => 'C', 'correct' => false]], 'scoring' => 'partial'],
        QuestionType::TrueFalse => ['correct' => true],
        QuestionType::Numeric => ['correct' => '3.14', 'tolerance' => '0.01', 'unit' => 'cm'],
        QuestionType::Text => ['max_length' => 200, 'reference_answer' => 'The model answer'],
        QuestionType::Ordering => ['items' => [['text' => 'First'], ['text' => 'Second'], ['text' => 'Third']], 'scoring' => 'all_or_nothing'],
        QuestionType::Matching => [
            'pairs' => [['left' => ['text' => 'Georgia'], 'right' => ['text' => 'Tbilisi']], ['left' => ['text' => 'France'], 'right' => ['text' => 'Paris']]],
            'distractors' => [['text' => 'Rome']],
        ],
    };
}

/**
 * Values that would give the answer away if they appeared in a player payload.
 *
 * @return list<string>
 */
function secretValues(QuestionDefinition $definition): array
{
    $data = $definition->toArray();

    return array_values(array_filter([
        is_string($data['reference_answer'] ?? null) ? $data['reference_answer'] : null,
        $definition->type() === QuestionType::Numeric ? (string) $data['correct'] : null,
        $definition->type() === QuestionType::Numeric ? (string) $data['tolerance'] : null,
    ]));
}

it('registers exactly one handler for every question type', function (): void {
    $registry = app(QuestionTypeRegistry::class);

    expect($registry->types())->toEqualCanonicalizing(QuestionType::cases());

    foreach (QuestionType::cases() as $type) {
        expect($registry->for($type)->type())->toBe($type);
    }
});

it('normalizes into the Stage 2 value object of its type', function (QuestionType $type): void {
    $definition = QuestionHandlers::normalize($type, editorInput($type));

    expect($definition)->toBeInstanceOf(Definitions::classFor($type))
        ->and(Definitions::fromArray($type, $definition->toArray()))->toEqual($definition);
})->with(QuestionType::cases());

it('round-trips through the editor input (edit page), keeping ids', function (QuestionType $type): void {
    $handler = QuestionHandlers::for($type);
    $definition = QuestionHandlers::normalize($type, editorInput($type));
    $again = $handler->normalizeDefinition($handler->toEditorInput($definition));

    // Ordering and matching store their items shuffled: compare everything but that order.
    $normalize = function (array $data): array {
        foreach (['items', 'right'] as $key) {
            if (isset($data[$key])) {
                usort($data[$key], fn (array $a, array $b): int => strcmp($a['id'], $b['id']));
            }
        }

        return $data;
    };

    expect($normalize($again->toArray()))->toBe($normalize($definition->toArray()));
})->with(QuestionType::cases());

it('drops ids from the editor input on request (duplicating), so new ones are generated', function (QuestionType $type): void {
    $handler = QuestionHandlers::for($type);
    $definition = QuestionHandlers::normalize($type, editorInput($type));

    expect(json_encode($handler->toEditorInput($definition, withIds: false)))->not->toContain('"id"');
})->with([QuestionType::SingleChoice, QuestionType::MultipleChoice, QuestionType::Ordering, QuestionType::Matching]);

it('generates fresh random ids each time (no sequential or reused ids)', function (QuestionType $type): void {
    $first = json_encode(QuestionHandlers::normalize($type, editorInput($type))->toArray());
    $second = json_encode(QuestionHandlers::normalize($type, editorInput($type))->toArray());

    expect($first)->not->toBe($second);
})->with([QuestionType::SingleChoice, QuestionType::MultipleChoice, QuestionType::Ordering, QuestionType::Matching]);

it('never leaks the answer in the player payload', function (QuestionType $type, bool $shuffle): void {
    $definition = QuestionHandlers::normalize($type, editorInput($type));
    $payload = QuestionHandlers::for($type)->toPlayerPayload($definition, Randomizers::secure(), $shuffle);

    expect(PayloadLeakScanner::findings($payload, secretValues($definition)))->toBe([]);
})->with(QuestionType::cases())->with([true, false]);

it('keeps the review payload free of the answer unless revealing', function (QuestionType $type): void {
    $definition = QuestionHandlers::normalize($type, editorInput($type));
    $review = QuestionHandlers::for($type)->toReviewPayload($definition, null, false);

    expect(PayloadLeakScanner::findings($review, secretValues($definition)))->toBe([]);
})->with(QuestionType::cases());

it('grades the factory definitions of every type', function (QuestionType $type): void {
    $question = Question::factory()->ofType($type)->make();
    $result = QuestionHandlers::for($type)->grade($question->definition, null, App\Domain\Questions\Points::of('1'));

    expect($result->awardedPoints->isZero())->toBeTrue();
})->with(QuestionType::cases());

it('rejects a definition of another type', function (): void {
    expect(fn () => QuestionHandlers::for(QuestionType::SingleChoice)->grade(new TrueFalseDefinition(true), null, App\Domain\Questions\Points::of('1')))
        ->toThrow(LogicException::class);
});

it('the leak scanner itself catches leaks', function (): void {
    expect(PayloadLeakScanner::findings(['options' => [['id' => 'A', 'text' => 'x', 'is_correct' => true]]]))->toBe(['forbidden key at $.options.0.is_correct'])
        ->and(PayloadLeakScanner::findings(['meta' => ['correctOrder' => []]]))->not->toBe([])
        ->and(PayloadLeakScanner::findings(['unit' => 'cm', 'hint' => '3.14'], ['3.14']))->toBe(['secret value at $.hint']);
});
