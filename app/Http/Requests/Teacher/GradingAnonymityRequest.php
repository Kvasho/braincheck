<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class GradingAnonymityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AttemptsGradeOwnTests->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'anonymous' => ['required', 'boolean'],
        ];
    }

    public function anonymous(): bool
    {
        return $this->boolean('anonymous');
    }
}
