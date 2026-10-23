<?php

declare(strict_types=1);

namespace App\Http\Requests\Student;

use App\Enums\Permission;
use App\Http\Requests\ListingRequest;

final class ResultIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::ResultsViewOwn->value) === true;
    }

    protected function filterRules(): array
    {
        return [];
    }
}
