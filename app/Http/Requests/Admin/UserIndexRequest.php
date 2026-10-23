<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Http\Requests\ListingRequest;
use App\Models\User;
use Illuminate\Validation\Rule;

final class UserIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', User::class) === true;
    }

    protected function filterRules(): array
    {
        return [
            'role' => ['nullable', Rule::enum(Role::class)],
            'status' => ['nullable', Rule::in(['active', 'blocked'])],
            'verified' => ['nullable', Rule::in(['yes', 'no'])],
        ];
    }

    /**
     * @return array{role: Role|null, status: string|null, verified: string|null}
     */
    public function filters(): array
    {
        return [
            'role' => $this->enum('role', Role::class),
            'status' => $this->string('status')->toString() ?: null,
            'verified' => $this->string('verified')->toString() ?: null,
        ];
    }
}
