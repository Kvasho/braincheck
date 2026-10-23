<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AttemptQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A question frozen into an attempt. `snapshot` (secret, $hidden) holds the
 * type, body, explanation, definition, points and version at start time;
 * grading and review read the snapshot, never the live question.
 *
 * @property int $id
 * @property int $attempt_id
 * @property int|null $question_id
 * @property int $position
 * @property array<string, mixed> $snapshot
 * @property string $points
 */
class AttemptQuestion extends Model
{
    /** @use HasFactory<AttemptQuestionFactory> */
    use HasFactory;

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [];

    /** @var list<string> */
    protected $hidden = ['id', 'snapshot'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'snapshot' => 'array',
            'points' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Attempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    /**
     * The live question (may be soft-deleted, or null if removed). For display
     * of history use the snapshot instead.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /**
     * @return HasOne<AttemptAnswer, $this>
     */
    public function answer(): HasOne
    {
        return $this->hasOne(AttemptAnswer::class);
    }
}
