<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Domain\Results\ResultsVisibilityPolicy;
use App\Models\Attempt;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A row of the student's results history. Score and pass result only when
 * ResultsVisibilityPolicy allows it. Requires `test`.
 *
 * @mixin Attempt
 */
final class StudentResultRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $policy = app(ResultsVisibilityPolicy::class);
        $visible = $policy->canSeeScore($this->resource);

        return [
            'ulid' => $this->ulid,
            'test' => $this->test->title,
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'visible' => $visible,
            'awaiting_review' => $policy->awaitsReview($this->resource),
            'percent' => $visible ? $this->score_percent : null,
            'passed' => $visible ? $this->passed : null,
        ];
    }
}
