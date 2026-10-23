<?php

declare(strict_types=1);

namespace App\Domain\Questions\Definitions;

use App\Enums\QuestionType;
use App\Exceptions\InvalidQuestionDefinition;

/**
 * `{ left: [{id, text}], right: [{id, text}], pairs: [{left, right}] }`.
 *
 * Every left entry is paired exactly once, each right entry is used at most
 * once, and `right` may contain extra distractors.
 */
final readonly class MatchingDefinition implements QuestionDefinition
{
    /**
     * @param  list<Item>  $left
     * @param  list<Item>  $right
     * @param  list<array{left: string, right: string}>  $pairs
     */
    public function __construct(
        public array $left,
        public array $right,
        public array $pairs,
    ) {
        if (count($left) < 2) {
            throw InvalidQuestionDefinition::because('matching needs at least 2 left entries.');
        }

        if (count($right) < count($left)) {
            throw InvalidQuestionDefinition::because('right must have at least as many entries as left.');
        }

        $leftIds = Guard::ids($left);
        $rightIds = Guard::ids($right);
        Guard::uniqueIds($leftIds, 'left');
        Guard::uniqueIds($rightIds, 'right');
        Guard::uniqueIds([...$leftIds, ...$rightIds], 'left and right');

        $pairedLeft = array_column($pairs, 'left');
        $pairedRight = array_column($pairs, 'right');
        Guard::uniqueIds($pairedLeft, 'pairs.left');
        Guard::uniqueIds($pairedRight, 'pairs.right');

        $sortedLeft = $leftIds;
        $sortedPairedLeft = $pairedLeft;
        sort($sortedLeft);
        sort($sortedPairedLeft);

        if ($sortedLeft !== $sortedPairedLeft) {
            throw InvalidQuestionDefinition::because('every left entry must be paired exactly once.');
        }

        if (array_diff($pairedRight, $rightIds) !== []) {
            throw InvalidQuestionDefinition::because('pairs must reference existing right entries.');
        }
    }

    public function type(): QuestionType
    {
        return QuestionType::Matching;
    }

    public static function fromArray(array $data): static
    {
        Guard::onlyKeys($data, ['left', 'right', 'pairs']);

        $pairs = $data['pairs'] ?? null;

        if (! is_array($pairs) || ! array_is_list($pairs)) {
            throw InvalidQuestionDefinition::because('pairs must be a list.');
        }

        $parsed = [];

        foreach ($pairs as $pair) {
            if (! is_array($pair) || array_keys($pair) !== ['left', 'right']
                || ! ShortId::isValid($pair['left']) || ! ShortId::isValid($pair['right'])) {
                throw InvalidQuestionDefinition::because('each pair must be { left: id, right: id }.');
            }

            /** @var array{left: string, right: string} $pair */
            $parsed[] = ['left' => $pair['left'], 'right' => $pair['right']];
        }

        return new self(
            Guard::items($data['left'] ?? null, 'left', 2),
            Guard::items($data['right'] ?? null, 'right', 2),
            $parsed,
        );
    }

    public function toArray(): array
    {
        return [
            'left' => array_map(fn (Item $item): array => $item->toArray(), $this->left),
            'right' => array_map(fn (Item $item): array => $item->toArray(), $this->right),
            'pairs' => $this->pairs,
        ];
    }
}
