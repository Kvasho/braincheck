<?php

declare(strict_types=1);

namespace App\Domain\Tests;

use App\Enums\QuestionSelectionMode;
use App\Enums\ResultsVisibility;
use App\Models\Category;
use Carbon\CarbonImmutable;

/**
 * Validated builder input (everything but the question list). Times are UTC.
 */
final readonly class TestSettings
{
    public function __construct(
        public string $title,
        public ?string $description,
        public ?Category $category,
        public ?int $timeLimitMinutes,
        public ?int $maxAttempts,
        public int $passPercent,
        public bool $shuffleQuestions,
        public bool $shuffleOptions,
        public QuestionSelectionMode $selectionMode,
        public ?int $randomQuestionCount,
        public ?CarbonImmutable $opensAt,
        public ?CarbonImmutable $closesAt,
        public ResultsVisibility $resultsVisibility,
        public bool $showCorrectAnswers,
        public bool $issuesCertificate,
    ) {}
}
