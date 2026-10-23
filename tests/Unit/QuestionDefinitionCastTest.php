<?php

declare(strict_types=1);

use App\Casts\QuestionDefinitionCast;
use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\ShortId;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;
use Illuminate\Database\Eloquent\Model;

/**
 * In-memory model with the cast (the Question model arrives in 2.7; no DB needed).
 */
function definitionModel(?string $type = null): Model
{
    $model = new class extends Model
    {
        protected $guarded = [];

        protected function casts(): array
        {
            return ['definition' => QuestionDefinitionCast::class];
        }
    };

    if ($type !== null) {
        $model->setAttribute('type', $type);
    }

    return $model;
}

/**
 * A model "loaded from the database" with the given raw column values.
 *
 * @param  array<string, mixed>  $raw
 */
function hydratedDefinitionModel(array $raw): Model
{
    $model = definitionModel();
    $model->setRawAttributes($raw, sync: true);

    return $model;
}

/**
 * A valid definition per type (fixed, valid short ids).
 *
 * @return array<string, mixed>
 */
function validDefinition(QuestionType $type): array
{
    return match ($type) {
        QuestionType::SingleChoice => [
            'options' => [['id' => 'ABCDEFGH', 'text' => 'თბილისი'], ['id' => 'KLMNPQRS', 'text' => 'Batumi']],
            'correct' => 'ABCDEFGH',
        ],
        QuestionType::MultipleChoice => [
            'options' => [
                ['id' => 'ABCDEFGH', 'text' => '2'],
                ['id' => 'KLMNPQRS', 'text' => '3'],
                ['id' => 'TUVWXYZ2', 'text' => '4'],
            ],
            'correct' => ['ABCDEFGH', 'KLMNPQRS'],
            'scoring' => 'partial',
        ],
        QuestionType::TrueFalse => ['correct' => false],
        QuestionType::Text => ['max_length' => 500, 'reference_answer' => 'ფოტოსინთეზი'],
        QuestionType::Numeric => ['correct' => '9.81', 'tolerance' => '0.05', 'unit' => 'm/s²'],
        QuestionType::Ordering => [
            'items' => [['id' => 'ABCDEFGH', 'text' => 'First'], ['id' => 'KLMNPQRS', 'text' => 'Second'], ['id' => 'TUVWXYZ2', 'text' => 'Third']],
            'correct_order' => ['KLMNPQRS', 'ABCDEFGH', 'TUVWXYZ2'],
            'scoring' => 'partial',
        ],
        QuestionType::Matching => [
            'left' => [['id' => 'ABCDEFGH', 'text' => 'საქართველო'], ['id' => 'KLMNPQRS', 'text' => 'France']],
            'right' => [['id' => 'TUVWXYZ2', 'text' => 'თბილისი'], ['id' => '3456789A', 'text' => 'Paris'], ['id' => 'BCDEFGHJ', 'text' => 'Rome']],
            'pairs' => [['left' => 'ABCDEFGH', 'right' => 'TUVWXYZ2'], ['left' => 'KLMNPQRS', 'right' => '3456789A']],
        ],
    };
}

describe('round trip', function (): void {
    it('stores and restores every type through the cast', function (QuestionType $type): void {
        $model = definitionModel($type->value);
        $model->setAttribute('definition', validDefinition($type));

        $stored = $model->getAttributes()['definition'];
        expect($stored)->toBeString()->toContain('"');

        $loaded = hydratedDefinitionModel(['type' => $type->value, 'definition' => $stored]);
        $definition = $loaded->getAttribute('definition');

        expect($definition)->toBeInstanceOf(Definitions::classFor($type))
            ->and($definition->type())->toBe($type)
            ->and($definition->toArray())->toBe(validDefinition($type));
    })->with(QuestionType::cases());

    it('validates value objects built in code, not only arrays', function (): void {
        new SingleChoiceDefinition([Item::make('Yes'), Item::make('No')], 'NOTANOPT');
    })->throws(InvalidQuestionDefinition::class, 'must be one of the options');

    it('round-trips a value object built in code', function (): void {
        $yes = Item::make('დიახ');
        $no = Item::make('არა');
        $model = definitionModel(QuestionType::SingleChoice->value);

        $model->setAttribute('definition', new SingleChoiceDefinition([$yes, $no], $yes->id));

        $loaded = hydratedDefinitionModel(['type' => 'single_choice', 'definition' => $model->getAttributes()['definition']]);

        expect($loaded->getAttribute('definition')->correct)->toBe($yes->id);
    });

    it('keeps Georgian text readable in the stored JSON', function (): void {
        $model = definitionModel(QuestionType::Text->value);
        $model->setAttribute('definition', validDefinition(QuestionType::Text));

        expect($model->getAttributes()['definition'])->toContain('ფოტოსინთეზი');
    });

    it('keeps null as null', function (): void {
        $model = definitionModel(QuestionType::TrueFalse->value);
        $model->setAttribute('definition', null);

        expect($model->getAttribute('definition'))->toBeNull();
    });
});

describe('rejecting malformed data', function (): void {
    it('rejects invalid shapes on write', function (QuestionType $type, array $definition): void {
        definitionModel($type->value)->setAttribute('definition', $definition);
    })->throws(InvalidQuestionDefinition::class)->with([
        'single: sequential ids' => [QuestionType::SingleChoice, ['options' => [['id' => '1', 'text' => 'a'], ['id' => '2', 'text' => 'b']], 'correct' => '1']],
        'single: correct not an option' => [QuestionType::SingleChoice, [...validDefinition(QuestionType::SingleChoice), 'correct' => 'ZZZZZZZZ']],
        'single: one option' => [QuestionType::SingleChoice, ['options' => [['id' => 'ABCDEFGH', 'text' => 'a']], 'correct' => 'ABCDEFGH']],
        'single: duplicate ids' => [QuestionType::SingleChoice, ['options' => [['id' => 'ABCDEFGH', 'text' => 'a'], ['id' => 'ABCDEFGH', 'text' => 'b']], 'correct' => 'ABCDEFGH']],
        'single: empty option text' => [QuestionType::SingleChoice, ['options' => [['id' => 'ABCDEFGH', 'text' => ' '], ['id' => 'KLMNPQRS', 'text' => 'b']], 'correct' => 'ABCDEFGH']],
        'single: unknown key' => [QuestionType::SingleChoice, [...validDefinition(QuestionType::SingleChoice), 'hint' => 'x']],
        'multiple: empty correct' => [QuestionType::MultipleChoice, [...validDefinition(QuestionType::MultipleChoice), 'correct' => []]],
        'multiple: bad scoring' => [QuestionType::MultipleChoice, [...validDefinition(QuestionType::MultipleChoice), 'scoring' => 'some']],
        'multiple: correct outside options' => [QuestionType::MultipleChoice, [...validDefinition(QuestionType::MultipleChoice), 'correct' => ['ZZZZZZZZ']]],
        'true/false: not a bool' => [QuestionType::TrueFalse, ['correct' => 'true']],
        'text: max_length 0' => [QuestionType::Text, ['max_length' => 0, 'reference_answer' => null]],
        'text: max_length too big' => [QuestionType::Text, ['max_length' => 1_000_000, 'reference_answer' => null]],
        'numeric: negative tolerance' => [QuestionType::Numeric, ['correct' => '1', 'tolerance' => '-0.1', 'unit' => null]],
        'numeric: not a decimal' => [QuestionType::Numeric, ['correct' => 'abc', 'tolerance' => '0', 'unit' => null]],
        'numeric: exponent' => [QuestionType::Numeric, ['correct' => '1e3', 'tolerance' => '0', 'unit' => null]],
        'numeric: boolean' => [QuestionType::Numeric, ['correct' => true, 'tolerance' => '0', 'unit' => null]],
        'ordering: bad scoring' => [QuestionType::Ordering, [...validDefinition(QuestionType::Ordering), 'scoring' => 'some']],
        'ordering: not a permutation' => [QuestionType::Ordering, [...validDefinition(QuestionType::Ordering), 'correct_order' => ['ABCDEFGH', 'KLMNPQRS']]],
        'ordering: repeated id' => [QuestionType::Ordering, [...validDefinition(QuestionType::Ordering), 'correct_order' => ['ABCDEFGH', 'ABCDEFGH', 'TUVWXYZ2']]],
        'matching: unpaired left' => [QuestionType::Matching, [...validDefinition(QuestionType::Matching), 'pairs' => [['left' => 'ABCDEFGH', 'right' => 'TUVWXYZ2']]]],
        'matching: right used twice' => [QuestionType::Matching, [...validDefinition(QuestionType::Matching), 'pairs' => [['left' => 'ABCDEFGH', 'right' => 'TUVWXYZ2'], ['left' => 'KLMNPQRS', 'right' => 'TUVWXYZ2']]]],
        'matching: unknown right' => [QuestionType::Matching, [...validDefinition(QuestionType::Matching), 'pairs' => [['left' => 'ABCDEFGH', 'right' => 'TUVWXYZ2'], ['left' => 'KLMNPQRS', 'right' => 'ZZZZZZZZ']]]],
        'matching: fewer right than left' => [QuestionType::Matching, [
            'left' => [['id' => 'ABCDEFGH', 'text' => 'a'], ['id' => 'KLMNPQRS', 'text' => 'b']],
            'right' => [['id' => 'TUVWXYZ2', 'text' => 'c']],
            'pairs' => [['left' => 'ABCDEFGH', 'right' => 'TUVWXYZ2']],
        ]],
        'matching: shape of another type' => [QuestionType::Matching, validDefinition(QuestionType::SingleChoice)],
    ]);

    it('reads numeric definitions stored as JSON numbers (before Stage 4) as exact decimal strings', function (): void {
        $definition = hydratedDefinitionModel([
            'type' => 'numeric',
            'definition' => '{"correct": 9.81, "tolerance": 0.1, "unit": null}',
        ])->getAttribute('definition');

        expect($definition->correct)->toBe('9.81')->and($definition->tolerance)->toBe('0.1');
    });

    it('reads ordering definitions without scoring (before Stage 4) as all or nothing', function (): void {
        $stored = validDefinition(QuestionType::Ordering);
        unset($stored['scoring']);

        $definition = hydratedDefinitionModel(['type' => 'ordering', 'definition' => json_encode($stored)])->getAttribute('definition');

        expect($definition->scoring)->toBe(App\Enums\MultipleChoiceScoring::AllOrNothing);
    });

    it('rejects malformed JSON on read', function (): void {
        hydratedDefinitionModel(['type' => 'true_false', 'definition' => '{not json'])->getAttribute('definition');
    })->throws(InvalidQuestionDefinition::class, 'malformed JSON');

    it('rejects stored data that does not match the type on read', function (): void {
        hydratedDefinitionModel([
            'type' => 'ordering',
            'definition' => json_encode(validDefinition(QuestionType::SingleChoice)),
        ])->getAttribute('definition');
    })->throws(InvalidQuestionDefinition::class);

    it('rejects a value object of another type', function (): void {
        definitionModel(QuestionType::SingleChoice->value)->setAttribute('definition', new TrueFalseDefinition(true));
    })->throws(InvalidQuestionDefinition::class, "can't be stored on a single_choice question");

    it('requires a valid type before the definition', function (?string $type): void {
        definitionModel($type)->setAttribute('definition', ['correct' => true]);
    })->throws(InvalidQuestionDefinition::class, 'valid question type')->with([null, 'essay']);
});

describe('short ids', function (): void {
    it('generates valid, unambiguous ids', function (): void {
        $ids = array_map(fn (): string => ShortId::generate(), range(1, 500));

        foreach ($ids as $id) {
            expect(ShortId::isValid($id))->toBeTrue()
                ->and($id)->not->toMatch('/[01IO]/');
        }

        expect(count(array_unique($ids)))->toBe(500);
    });

    it('rejects indexes, sequential numbers and wrong lengths', function (mixed $id): void {
        expect(ShortId::isValid($id))->toBeFalse();
    })->with([0, '1', 'A', 'abcdefgh', 'ABCDEFGHJ', 'ABCDEF0H', null]);
});
