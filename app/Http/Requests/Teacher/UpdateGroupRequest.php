<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\InviteExpiry;
use App\Models\Group;

final class UpdateGroupRequest extends GroupRequest
{
    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group && $this->user()?->can('update', $group) === true;
    }

    protected function expiryChoices(): array
    {
        return InviteExpiry::cases();
    }
}
