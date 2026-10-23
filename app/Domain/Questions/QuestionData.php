<?php

declare(strict_types=1);

namespace App\Domain\Questions;

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Shared\RichTextDocument;
use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;

/**
 * Validated question editor input, ready for CreateQuestion / UpdateQuestion.
 * The definition is already normalized by the type's handler.
 */
final readonly class QuestionData
{
    /**
     * @param  list<Tag>  $tags
     */
    public function __construct(
        public QuestionType $type,
        public RichTextDocument $body,
        public ?RichTextDocument $explanation,
        public ?Category $category,
        public array $tags,
        public Difficulty $difficulty,
        public Points $points,
        public QuestionDefinition $definition,
        public ?UploadedFile $image = null,
        public bool $removeImage = false,
    ) {}
}
