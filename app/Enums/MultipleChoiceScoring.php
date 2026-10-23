<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * How a multiple-choice or ordering question is scored: full points only for a
 * fully correct answer, or partial credit (formulas in docs/question-types.md).
 */
enum MultipleChoiceScoring: string implements HasLabel
{
    case AllOrNothing = 'all_or_nothing';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::AllOrNothing => __('All or nothing'),
            self::Partial => __('Partial credit'),
        };
    }
}
