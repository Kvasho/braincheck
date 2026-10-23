<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Enums\InviteExpiry;
use App\Models\Group;

final class StoreGroupRequest extends GroupRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Group::class) === true;
    }

    protected function expiryChoices(): array
    {
        return [InviteExpiry::Never, InviteExpiry::OneDay, InviteExpiry::SevenDays, InviteExpiry::ThirtyDays, InviteExpiry::Custom];
    }
}
