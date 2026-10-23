<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Exceptions\InvalidQuestionDefinition;

/**
 * An option, ordering item or matching side entry: `{ id, text }`.
 */
final readonly class Item
{
    public const MAX_TEXT_LENGTH = 1000;

    public function __construct(
        public string $id,
        public string $text,
    ) {
        if (! ShortId::isValid($id)) {
            throw InvalidQuestionDefinition::because('item ids must be '.ShortId::LENGTH.'-character short ids.');
        }

        if (trim($text) === '' || mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw InvalidQuestionDefinition::because('item text must be 1–'.self::MAX_TEXT_LENGTH.' characters.');
        }
    }

    /**
     * Build an item with a fresh random id.
     */
    public static function make(string $text): self
    {
        return new self(ShortId::generate(), $text);
    }

    public static function fromArray(mixed $data): self
    {
        if (! is_array($data) || ! is_string($data['id'] ?? null) || ! is_string($data['text'] ?? null)) {
            throw InvalidQuestionDefinition::because('each item must be { id: string, text: string }.');
        }

        return new self($data['id'], $data['text']);
    }

    /**
     * @return array{id: string, text: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'text' => $this->text];
    }
}
