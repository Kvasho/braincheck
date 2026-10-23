<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AttemptAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The single answer row of an attempt question, updated by autosave. Only the
 * answer itself is mass assignable; grading fields are set by Actions.
 *
 * @property int $id
 * @property int $attempt_question_id
 * @property array<string, mixed>|null $answer
 * @property CarbonImmutable|null $answered_at
 * @property bool $flagged
 * @property bool|null $is_correct
 * @property string|null $awarded_points
 * @property bool $needs_manual_grading
 * @property int|null $graded_by
 * @property CarbonImmutable|null $graded_at
 * @property string|null $feedback
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class AttemptAnswer extends Model
{
    /** @use HasFactory<AttemptAnswerFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['answer', 'answered_at'];

    /** @var list<string> */
    protected $hidden = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'answer' => null,
        'answered_at' => null,
        'flagged' => false,
        'is_correct' => null,
        'awarded_points' => null,
        'needs_manual_grading' => false,
        'graded_by' => null,
        'graded_at' => null,
        'feedback' => null,
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'answered_at' => 'immutable_datetime',
            'flagged' => 'boolean',
            'is_correct' => 'boolean',
            'awarded_points' => 'decimal:2',
            'needs_manual_grading' => 'boolean',
            'graded_at' => 'immutable_datetime',
        ];
    }

    /**
     * A token of the current grading state. A grader sends back the token they
     * loaded; a different one means someone else graded meanwhile (conflict).
     */
    public function gradingVersion(): string
    {
        return hash('sha256', (string) json_encode([$this->awarded_points, $this->graded_by, $this->graded_at?->toIso8601String(), $this->feedback, $this->is_correct]));
    }

    /**
     * @return BelongsTo<AttemptQuestion, $this>
     */
    public function attemptQuestion(): BelongsTo
    {
        return $this->belongsTo(AttemptQuestion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}
