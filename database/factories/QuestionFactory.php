<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Questions\Definitions\Item;
use App\Domain\Questions\Definitions\MatchingDefinition;
use App\Domain\Questions\Definitions\MultipleChoiceDefinition;
use App\Domain\Questions\Definitions\NumericDefinition;
use App\Domain\Questions\Definitions\OrderingDefinition;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\SingleChoiceDefinition;
use App\Domain\Questions\Definitions\TextDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Domain\Shared\RichTextDocument;
use App\Enums\Difficulty;
use App\Enums\MultipleChoiceScoring;
use App\Enums\QuestionType;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Arr;

/**
 * Questions owned by a Teacher, each with a valid definition for its type
 * (random short ids via Item::make()).
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->teacher(),
            'category_id' => null,
            // `type` before `definition`: the definition cast reads the type.
            'type' => QuestionType::SingleChoice,
            'difficulty' => fake()->randomElement(Difficulty::cases()),
            'body' => RichTextDocument::fromPlainText(fake()->sentence(8).'?'),
            'explanation' => fake()->boolean(60) ? RichTextDocument::fromPlainText(fake()->sentence(12)) : null,
            'definition' => fn (array $attributes): QuestionDefinition => self::definitionFor(
                $attributes['type'] instanceof QuestionType ? $attributes['type'] : QuestionType::from($attributes['type']),
            ),
            'points' => fake()->randomElement(['1.00', '1.00', '2.00', '3.00']),
            'version' => 1,
        ];
    }

    /**
     * A valid, generic definition for a question type.
     */
    public static function definitionFor(QuestionType $type): QuestionDefinition
    {
        $items = fn (int $count): array => array_map(fn (): Item => Item::make(ucfirst(fake()->word().' '.fake()->word())), range(1, $count));

        switch ($type) {
            case QuestionType::SingleChoice:
                $options = $items(4);

                return new SingleChoiceDefinition($options, fake()->randomElement($options)->id);

            case QuestionType::MultipleChoice:
                $options = $items(5);
                $correct = array_values(array_map(fn (Item $item): string => $item->id, fake()->randomElements($options, 2)));

                return new MultipleChoiceDefinition($options, $correct, fake()->randomElement(MultipleChoiceScoring::cases()));

            case QuestionType::TrueFalse:
                return new TrueFalseDefinition(fake()->boolean());

            case QuestionType::Text:
                return new TextDefinition(fake()->randomElement([200, 500, 1000]), fake()->optional()->sentence());

            case QuestionType::Numeric:
                return new NumericDefinition((string) fake()->numberBetween(1, 500).'.'.fake()->numberBetween(10, 99), fake()->randomElement(['0', '0.01', '0.5']), fake()->optional()->randomElement(['m', 'kg', 's', '%']));

            case QuestionType::Ordering:
                $ordered = $items(4);
                // Arr::shuffle uses a CSPRNG (the arch security preset forbids shuffle()).
                $shuffled = array_values(Arr::shuffle($ordered));

                return new OrderingDefinition($shuffled, array_map(fn (Item $item): string => $item->id, $ordered), fake()->randomElement(MultipleChoiceScoring::cases()));

            case QuestionType::Matching:
                $left = $items(3);
                $right = $items(4);

                return new MatchingDefinition($left, $right, array_map(
                    fn (Item $l, Item $r): array => ['left' => $l->id, 'right' => $r->id],
                    $left,
                    array_slice($right, 0, 3),
                ));
        }
    }

    public function ofType(QuestionType $type): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'definition' => self::definitionFor($type),
        ]);
    }

    public function singleChoice(): static
    {
        return $this->ofType(QuestionType::SingleChoice);
    }

    public function multipleChoice(): static
    {
        return $this->ofType(QuestionType::MultipleChoice);
    }

    public function trueFalse(): static
    {
        return $this->ofType(QuestionType::TrueFalse);
    }

    public function text(): static
    {
        return $this->ofType(QuestionType::Text);
    }

    public function numeric(): static
    {
        return $this->ofType(QuestionType::Numeric);
    }

    public function ordering(): static
    {
        return $this->ofType(QuestionType::Ordering);
    }

    public function matching(): static
    {
        return $this->ofType(QuestionType::Matching);
    }

    public function easy(): static
    {
        return $this->state(fn (): array => ['difficulty' => Difficulty::Easy]);
    }

    public function medium(): static
    {
        return $this->state(fn (): array => ['difficulty' => Difficulty::Medium]);
    }

    public function hard(): static
    {
        return $this->state(fn (): array => ['difficulty' => Difficulty::Hard]);
    }

    public function ownedBy(User $owner): static
    {
        return $this->state(fn (): array => ['owner_id' => $owner->id]);
    }
}
