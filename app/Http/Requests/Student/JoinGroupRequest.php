<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Enums\Permission;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only shape checks here: everything about the code itself (unknown, expired,
 * disabled) fails inside JoinGroup with one generic message.
 */
final class JoinGroupRequest extends FormRequest
{
    public const int CODE_MAX = 32;

    public function authorize(): bool
    {
        return $this->user()?->can(Permission::GroupsJoin->value) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:'.self::CODE_MAX],
        ];
    }

    public function code(): string
    {
        return $this->string('code')->toString();
    }
}
