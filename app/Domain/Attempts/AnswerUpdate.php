<?php

declare(strict_types=1);

namespace App\Domain\Attempts;

/**
 * One autosave: the answer (when sent; null clears it) and/or the flag.
 * Nothing else from the request is ever read (no times, scores or status).
 */
final readonly class AnswerUpdate
{
    /**
     * @param  array<array-key, mixed>|null  $answer
     */
    public function __construct(
        public bool $hasAnswer,
        public ?array $answer,
        public ?bool $flagged,
    ) {}
}
