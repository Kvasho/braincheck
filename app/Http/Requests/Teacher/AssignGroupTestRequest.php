<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Models\Group;
use App\Models\Test;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Assign a test (by ULID) to the group. Ownership and status are checked by
 * AssignTestToGroup: another teacher's test is a 403, a draft a 422.
 */
final class AssignGroupTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group && $this->user()?->can('update', $group) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test' => ['required', 'string', 'ulid'],
        ];
    }

    public function test(): Test
    {
        $test = Test::query()->where('ulid', $this->string('test')->toString())->first();

        if ($test === null) {
            abort(404);
        }

        return $test;
    }
}
