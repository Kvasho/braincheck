<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\Permission;
use App\Http\Requests\ListingRequest;

final class GroupIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::GroupsManageOwn->value) === true;
    }

    protected function filterRules(): array
    {
        return [
            'archived' => ['nullable', 'string', 'in:only'],
        ];
    }

    public function onlyArchived(): bool
    {
        return $this->string('archived')->toString() === 'only';
    }
}
