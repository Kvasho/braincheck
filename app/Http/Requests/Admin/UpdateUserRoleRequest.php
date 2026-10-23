<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The admin role can never be granted through the web: admins are created only
 * with `php artisan braincheck:make-admin` (demoting an admin is allowed).
 */
final class UpdateUserRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && $this->user()?->can('changeRole', $target) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)->except([Role::Admin])],
        ];
    }

    public function role(): Role
    {
        return $this->enum('role', Role::class) ?? throw new \LogicException('Validated role missing.');
    }
}
