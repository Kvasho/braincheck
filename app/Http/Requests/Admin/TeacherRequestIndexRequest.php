<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TeacherRequestStatus;
use App\Http\Requests\ListingRequest;
use App\Models\TeacherAccessRequest;
use Illuminate\Validation\Rule;

final class TeacherRequestIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', TeacherAccessRequest::class) === true;
    }

    protected function filterRules(): array
    {
        return [
            'status' => ['nullable', Rule::enum(TeacherRequestStatus::class)],
        ];
    }

    public function status(): ?TeacherRequestStatus
    {
        return $this->enum('status', TeacherRequestStatus::class);
    }
}
