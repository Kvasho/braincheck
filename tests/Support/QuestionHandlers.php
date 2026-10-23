<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\Types\QuestionTypeHandler;
use App\Domain\Questions\Types\QuestionTypeRegistry;
use App\Enums\QuestionType;
use Illuminate\Support\Facades\Validator;

/**
 * Helpers for the question type handler tests.
 */
final class QuestionHandlers
{
    public static function for(QuestionType $type): QuestionTypeHandler
    {
        return app(QuestionTypeRegistry::class)->for($type);
    }

    /**
     * Validation errors of editor input (empty array = valid).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, list<string>>
     */
    public static function definitionErrors(QuestionType $type, array $input): array
    {
        return Validator::make($input, self::for($type)->definitionRules())->errors()->toArray();
    }

    /**
     * Validation errors of a student answer (empty array = valid).
     *
     * @param  array<array-key, mixed>  $answer
     * @return array<string, list<string>>
     */
    public static function answerErrors(QuestionDefinition $definition, array $answer): array
    {
        return Validator::make($answer, self::for($definition->type())->answerRules($definition))->errors()->toArray();
    }

    /**
     * Validate then normalize, like the question editor will.
     *
     * @param  array<string, mixed>  $input
     */
    public static function normalize(QuestionType $type, array $input): QuestionDefinition
    {
        $errors = self::definitionErrors($type, $input);

        if ($errors !== []) {
            throw new \RuntimeException('Invalid test input: '.json_encode($errors));
        }

        return self::for($type)->normalizeDefinition($input);
    }
}
