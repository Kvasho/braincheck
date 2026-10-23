<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Models\Attempt;
use Illuminate\Foundation\Http\FormRequest;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A request on the route's {attempt}: only its owner may take it (real
 * attempts only). Anyone else gets a 404, before any validation, so the
 * response never confirms that someone else's attempt exists.
 */
abstract class AttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('take', $this->attempt()) === true;
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function rules(): array;

    public function attempt(): Attempt
    {
        $attempt = $this->route('attempt');

        if (! $attempt instanceof Attempt) {
            throw new NotFoundHttpException;
        }

        return $attempt;
    }

    protected function failedAuthorization(): void
    {
        throw new NotFoundHttpException;
    }
}
