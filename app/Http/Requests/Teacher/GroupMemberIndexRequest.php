<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Http\Requests\ListingRequest;
use App\Models\Group;

/**
 * The group page: its member listing (search, sort, pages) and the active tab.
 */
final class GroupMemberIndexRequest extends ListingRequest
{
    public const array TABS = ['overview', 'invite', 'members', 'tests'];

    public function authorize(): bool
    {
        $group = $this->route('group');

        return $group instanceof Group && $this->user()?->can('update', $group) === true;
    }

    protected function filterRules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:'.implode(',', self::TABS)],
        ];
    }

    public function tab(): string
    {
        return $this->string('tab')->toString() ?: 'overview';
    }
}
