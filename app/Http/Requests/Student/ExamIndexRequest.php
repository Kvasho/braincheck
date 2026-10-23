<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Enums\Permission;
use App\Models\Group;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

final class ExamIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ExamsTake->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'group' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * The group filter, only when the student is a member of it (anything else is ignored).
     */
    public function group(): ?Group
    {
        $ulid = $this->string('group')->toString();
        $user = $this->user();

        if ($ulid === '' || $user === null) {
            return null;
        }

        return $user->groups()->where('groups.ulid', $ulid)->first();
    }
}
