<?php

declare(strict_types=1);

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Definitions\TrueFalseDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Domain\Questions\Types\QuestionTypeHandler;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\QuestionType;
use App\Providers\QuestionTypeServiceProvider;
use Random\Randomizer;

/**
 * A minimal handler for registry tests.
 */
function fakeHandler(QuestionType $type): QuestionTypeHandler
{
    return new class($type) implements QuestionTypeHandler
    {
        public function __construct(private readonly QuestionType $handles) {}

        public function type(): QuestionType
        {
            return $this->handles;
        }

        public function definitionRules(): array
        {
            return [];
        }

        public function normalizeDefinition(array $input): QuestionDefinition
        {
            return new TrueFalseDefinition(true);
        }

        public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array
        {
            return [];
        }

        public function answerRules(QuestionDefinition $definition): array
        {
            return [];
        }

        public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult
        {
            return GradeResult::zero();
        }

        public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array
        {
            return [];
        }

        public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array
        {
            return [];
        }
    };
}

it('resolves a handler by type and by definition', function (): void {
    $trueFalse = fakeHandler(QuestionType::TrueFalse);
    $registry = new QuestionTypeRegistry([fakeHandler(QuestionType::SingleChoice), $trueFalse]);

    expect($registry->for(QuestionType::TrueFalse))->toBe($trueFalse)
        ->and($registry->forDefinition(new TrueFalseDefinition(false)))->toBe($trueFalse)
        ->and($registry->has(QuestionType::Matching))->toBeFalse()
        ->and($registry->types())->toBe([QuestionType::SingleChoice, QuestionType::TrueFalse]);
});

it('fails loudly for an unregistered type', function (): void {
    expect(fn () => (new QuestionTypeRegistry([]))->for(QuestionType::Numeric))
        ->toThrow(LogicException::class, 'numeric');
});

it('rejects two handlers for the same type', function (): void {
    expect(fn () => new QuestionTypeRegistry([fakeHandler(QuestionType::Text), fakeHandler(QuestionType::Text)]))
        ->toThrow(LogicException::class);
});

it('is a container singleton built from the provider list', function (): void {
    expect(app(QuestionTypeRegistry::class))->toBe(app(QuestionTypeRegistry::class))
        ->and(app(QuestionTypeRegistry::class)->types())->toHaveCount(count(QuestionTypeServiceProvider::HANDLERS));
});
