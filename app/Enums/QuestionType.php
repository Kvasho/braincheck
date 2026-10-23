<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Question types. Each type is a pluggable module (strategy class + Editor /
 * Player / Review components) from Stage 4 on; its content shape is documented
 * in docs/question-types.md.
 */
enum QuestionType: string implements HasLabel
{
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case TrueFalse = 'true_false';
    case Text = 'text';
    case Numeric = 'numeric';
    case Ordering = 'ordering';
    case Matching = 'matching';

    public function label(): string
    {
        return match ($this) {
            self::SingleChoice => __('Single choice'),
            self::MultipleChoice => __('Multiple choice'),
            self::TrueFalse => __('True / false'),
            self::Text => __('Text answer'),
            self::Numeric => __('Numeric answer'),
            self::Ordering => __('Ordering'),
            self::Matching => __('Matching'),
        };
    }

    /**
     * Whether answers can be graded automatically. Free-text answers need a teacher.
     */
    public function isAutoGradable(): bool
    {
        return $this !== self::Text;
    }
}
