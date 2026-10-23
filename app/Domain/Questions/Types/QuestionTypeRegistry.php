<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Enums\QuestionType;
use LogicException;

/**
 * Resolves the handler of a question type. Built once (singleton) by
 * QuestionTypeServiceProvider from its list of handler classes.
 */
final readonly class QuestionTypeRegistry
{
    /** @var array<string, QuestionTypeHandler> */
    private array $handlers;

    /**
     * @param  iterable<QuestionTypeHandler>  $handlers
     *
     * @throws LogicException if two handlers claim the same type
     */
    public function __construct(iterable $handlers)
    {
        $byType = [];

        foreach ($handlers as $handler) {
            $type = $handler->type()->value;

            if (isset($byType[$type])) {
                throw new LogicException("Two handlers are registered for question type [{$type}].");
            }

            $byType[$type] = $handler;
        }

        $this->handlers = $byType;
    }

    /**
     * @throws LogicException if no handler is registered for the type
     */
    public function for(QuestionType $type): QuestionTypeHandler
    {
        return $this->handlers[$type->value]
            ?? throw new LogicException("No handler is registered for question type [{$type->value}].");
    }

    /**
     * The handler for a definition (the pairing type ↔ definition is guaranteed).
     */
    public function forDefinition(QuestionDefinition $definition): QuestionTypeHandler
    {
        return $this->for($definition->type());
    }

    public function has(QuestionType $type): bool
    {
        return isset($this->handlers[$type->value]);
    }

    /**
     * @return list<QuestionType>
     */
    public function types(): array
    {
        return array_map(fn (string $type): QuestionType => QuestionType::from($type), array_keys($this->handlers));
    }
}
