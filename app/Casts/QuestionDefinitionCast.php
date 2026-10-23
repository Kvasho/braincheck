<?php

declare(strict_types=1);

namespace App\Casts;

use App\Domain\Questions\Definitions\Definitions;
use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use JsonException;

/**
 * Casts the JSON `definition` column to the value object matching the model's
 * `type` attribute. Malformed or mismatched data throws InvalidQuestionDefinition,
 * both when reading and when writing.
 *
 * @implements CastsAttributes<QuestionDefinition, QuestionDefinition|array<array-key, mixed>>
 */
final class QuestionDefinitionCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?QuestionDefinition
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            throw InvalidQuestionDefinition::because('stored definition is not a JSON string.');
        }

        try {
            $data = json_decode($value, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidQuestionDefinition('Invalid question definition: malformed JSON.', previous: $exception);
        }

        if (! is_array($data)) {
            throw InvalidQuestionDefinition::because('definition must be a JSON object.');
        }

        return Definitions::fromArray($this->type($attributes), $data);
    }

    /**
     * Accepts a QuestionDefinition (must match the type) or a raw array,
     * which is validated against the type's shape before it is stored.
     *
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        $type = $this->type($attributes);

        $definition = $value instanceof QuestionDefinition
            ? $value
            : Definitions::fromArray($type, $value);

        if ($definition->type() !== $type) {
            throw InvalidQuestionDefinition::because("a {$definition->type()->value} definition can't be stored on a {$type->value} question.");
        }

        return [$key => json_encode($definition->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION)];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function type(array $attributes): QuestionType
    {
        $type = $attributes['type'] ?? null;

        if ($type instanceof QuestionType) {
            return $type;
        }

        if (is_string($type) && ($resolved = QuestionType::tryFrom($type)) !== null) {
            return $resolved;
        }

        throw InvalidQuestionDefinition::because('set a valid question type before the definition.');
    }
}
