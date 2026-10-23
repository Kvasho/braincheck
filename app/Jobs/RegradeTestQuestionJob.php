<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Grading\RegradeTestQuestion;
use App\Domain\Questions\Definitions\Definitions;
use App\Enums\QuestionType;
use App\Models\AttemptQuestion;
use App\Models\Test;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Applies a fixed definition to every affected snapshot of one test question
 * version, in chunks (see RegradeTestQuestion). One regrade per test question
 * version at a time.
 */
final class RegradeTestQuestionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $definition
     */
    public function __construct(
        public readonly int $testId,
        public readonly int $questionId,
        public readonly int $version,
        public readonly string $type,
        public readonly array $definition,
    ) {}

    public function uniqueId(): string
    {
        return "{$this->testId}:{$this->questionId}:{$this->version}";
    }

    public function handle(RegradeTestQuestion $regrade): void
    {
        $test = Test::query()->withTrashed()->find($this->testId);

        if ($test === null) {
            return;
        }

        $definition = Definitions::fromArray(QuestionType::from($this->type), $this->definition);

        $regrade->affected($test, $this->questionId, $this->version)
            ->with('attempt')
            ->chunkById(RegradeTestQuestion::CHUNK, function (Collection $rows) use ($regrade, $definition): void {
                foreach ($rows as $row) {
                    /** @var AttemptQuestion $row */
                    $regrade->applyTo($row, $definition);
                }
            });
    }
}
