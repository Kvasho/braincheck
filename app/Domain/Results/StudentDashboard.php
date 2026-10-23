<?php

declare(strict_types=1);

namespace App\Domain\Results;

use App\Domain\Attempts\StudentExams;
use App\Models\Attempt;
use App\Models\User;

/**
 * The student dashboard: available exams, the attempt in progress, latest
 * results and a progress sparkline (visible results only). XP and level
 * are placeholders until Stage 11.
 */
final readonly class StudentDashboard
{
    public const int LATEST = 3;

    public const int SPARKLINE = 10;

    public function __construct(private StudentExams $exams, private StudentResults $results) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $student): array
    {
        $cards = collect($this->exams->cards($student));
        $inProgress = $cards->firstWhere('section', StudentExams::IN_PROGRESS);
        $visible = $this->results->visible($student);

        return [
            'available' => $cards->where('section', StudentExams::AVAILABLE)->count(),
            'in_progress' => $inProgress === null ? null : [
                'title' => $inProgress['title'],
                'attempt' => $inProgress['attempt'],
            ],
            'latest' => array_values($visible->reverse()->take(self::LATEST)->map(fn (Attempt $attempt): array => [
                'attempt' => $attempt->ulid,
                'test' => $attempt->test->title,
                'percent' => $attempt->score_percent,
                'passed' => $attempt->passed,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
            ])->all()),
            'sparkline' => array_slice(StudentResults::progress($visible), -self::SPARKLINE),
            // Stage 11 (gamification) fills these.
            'xp' => null,
            'level' => null,
        ];
    }
}
