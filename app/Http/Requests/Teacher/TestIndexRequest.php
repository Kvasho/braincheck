<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\Permission;
use App\Enums\TestStatus;
use App\Http\Requests\ListingRequest;
use Illuminate\Validation\Rule;

final class TestIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::TestsViewOwn->value) === true;
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TestStatus::class)],
            'category' => ['nullable', 'string', 'ulid'],
        ];
    }

    /**
     * @return array{status: TestStatus|null, category: string|null}
     */
    public function filters(): array
    {
        return [
            'status' => $this->enum('status', TestStatus::class),
            'category' => $this->string('category')->toString() ?: null,
        ];
    }
}
