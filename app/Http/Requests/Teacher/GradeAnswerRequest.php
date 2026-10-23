<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Domain\Questions\Points;
use App\Models\Attempt;
use App\Models\AttemptQuestion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Manual grade or override of the question at {position} in {attempt}:
 * points from 0 to the question's points in steps of 0.25 (the maximum
 * itself is always allowed). Range and state are checked again inside the
 * Action, under the row lock. No floats: Points / BigDecimal only.
 */
abstract class GradeAnswerRequest extends FormRequest
{
    public const string POINTS_PATTERN = '/^\d{1,4}(\.\d{1,2})?$/';

    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt instanceof Attempt && $this->user()?->can('grade', $attempt) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'points' => ['required', 'string', 'regex:'.self::POINTS_PATTERN],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('points')) {
                    return;
                }

                $points = $this->points();
                $max = Points::of($this->question()->points);

                if ($points->isGreaterThan($max)) {
                    $validator->errors()->add('points', __('At most :max points.', ['max' => $max->toString()]));

                    return;
                }

                $onGrid = $points->value->multipliedBy(4)->getFractionalPart()->isZero();

                if (! $onGrid && ! $points->equals($max)) {
                    $validator->errors()->add('points', __('Use steps of 0.25 points.'));
                }
            },
        ];
    }

    public function points(): Points
    {
        return Points::of($this->string('points')->toString());
    }

    public function question(): AttemptQuestion
    {
        /** @var Attempt $attempt */
        $attempt = $this->route('attempt');

        return AttemptQuestion::query()
            ->where('attempt_id', $attempt->id)
            ->where('position', (int) $this->route('position'))
            ->with('attempt')
            ->firstOrFail();
    }
}
