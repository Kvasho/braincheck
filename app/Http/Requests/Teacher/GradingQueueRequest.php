<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\Permission;
use App\Http\Requests\ListingRequest;

final class GradingQueueRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::AttemptsGradeOwnTests->value) === true;
    }

    protected function filterRules(): array
    {
        return [
            'test' => ['nullable', 'string', 'ulid'],
            'group' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array{test: string|null, group: string|null}
     */
    public function filters(): array
    {
        return [
            'test' => $this->string('test')->toString() ?: null,
            'group' => $this->string('group')->toString() ?: null,
        ];
    }
}
