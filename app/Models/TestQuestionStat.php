<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TestQuestionStatFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Precomputed analytics of one question in one test (real, graded attempts
 * only). Disposable: RecomputeTestQuestionStats rebuilds every row of a test.
 * Written only by that job (nothing is mass assignable).
 *
 * @property int $id
 * @property int $test_id
 * @property int $question_id
 * @property string $type
 * @property string $excerpt
 * @property int $responses
 * @property string|null $difficulty
 * @property string|null $discrimination
 * @property string|null $average_points
 * @property int $manual_grades
 * @property bool $needs_review
 * @property list<array<string, mixed>>|null $distractors
 * @property \Carbon\CarbonImmutable $computed_at
 */
class TestQuestionStat extends Model
{
    /** @use HasFactory<TestQuestionStatFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'responses' => 0,
        'difficulty' => null,
        'discrimination' => null,
        'average_points' => null,
        'manual_grades' => 0,
        'needs_review' => false,
        'distractors' => null,
    ];

    protected function casts(): array
    {
        return [
            'responses' => 'integer',
            'difficulty' => 'decimal:4',
            'discrimination' => 'decimal:4',
            'average_points' => 'decimal:2',
            'manual_grades' => 'integer',
            'needs_review' => 'boolean',
            'distractors' => 'array',
            'computed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Test, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }
}
