<?php

declare(strict_types=1);

namespace App\Domain\Questions\Types;

use App\Domain\Questions\Definitions\QuestionDefinition;
use App\Domain\Questions\GradeResult;
use App\Domain\Questions\Points;
use App\Enums\QuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Random\Randomizer;

/**
 * One question type as a pluggable "Synapse module" (backend half; the frontend
 * half is the Editor / Player / Review components in resources/js/question-types).
 *
 * Handlers are stateless and registered in QuestionTypeServiceProvider; resolve
 * them through QuestionTypeRegistry, never with `new`. Each handler receives
 * only definitions of its own type (QuestionTypeRegistry::for() guarantees the
 * pairing) and must reject any other with a LogicException.
 *
 * Security contract:
 * - toPlayerPayload() is what a student sees while answering. It must never
 *   contain correct-answer information, directly or derivably (no `correct`,
 *   `correct_order`, `pairs`, `is_correct`, `reference_answer`, `tolerance`,
 *   and no option order that mirrors the answer).
 * - All randomness comes from the given Randomizer (a CSPRNG unless a seeded one
 *   is explicitly needed); all point arithmetic uses Points, never floats.
 */
interface QuestionTypeHandler
{
    public function type(): QuestionType;

    /**
     * Validation rules for the `definition` payload of the question editor,
     * keyed relative to that payload (e.g. `options`, `options.*.text`).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function definitionRules(): array;

    /**
     * Turn validated editor input into the definition value object: generate
     * random short IDs for new options/items, keep existing valid IDs, trim text.
     *
     * @param  array<array-key, mixed>  $input
     */
    public function normalizeDefinition(array $input): QuestionDefinition;

    /**
     * The inverse of normalizeDefinition(): the editor input for an existing
     * definition (owner's edit page only: it contains the answer). With
     * $withIds = false the ids are left out, so normalizing again creates fresh
     * ones (duplicating a question).
     *
     * @return array<string, mixed>
     */
    public function toEditorInput(QuestionDefinition $definition, bool $withIds = true): array;

    /**
     * Validation rules for a student's answer to *this* definition (IDs must
     * exist, no duplicates, bounded sizes), keyed relative to the answer payload.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function answerRules(QuestionDefinition $definition): array;

    /**
     * Grade an answer that passed answerRules() (null = not answered).
     *
     * @param  array<array-key, mixed>|null  $answer
     */
    public function grade(QuestionDefinition $definition, ?array $answer, Points $points): GradeResult;

    /**
     * What a student sees while answering. Never any correct-answer information.
     *
     * @return array<string, mixed>
     */
    public function toPlayerPayload(QuestionDefinition $definition, Randomizer $randomizer, bool $shuffle): array;

    /**
     * What is shown after grading: the student's answer and, only when
     * $revealCorrect is true, the correct answer.
     *
     * @param  array<array-key, mixed>|null  $answer
     * @return array<string, mixed>
     */
    public function toReviewPayload(QuestionDefinition $definition, ?array $answer, bool $revealCorrect): array;
}
