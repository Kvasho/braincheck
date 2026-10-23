<?php

declare(strict_types=1);

namespace App\Http\Requests\Teacher;

use App\Http\Requests\ListingRequest;
use App\Models\Group;
use App\Models\Test;
use Carbon\CarbonImmutable;

/**
 * Analytics of a test: optional group (one the test is assigned to) and
 * submission date range (dates, inclusive), plus the attempts list paging.
 */
final class TestAnalyticsRequest extends ListingRequest
{
    public function authorize(): bool
    {
        $test = $this->route('test');

        return $test instanceof Test && $this->user()?->can('viewAnalytics', $test) === true;
    }

    protected function filterRules(): array
    {
        return [
            'group' => ['nullable', 'string', 'ulid'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{group: Group|null, from: CarbonImmutable|null, to: CarbonImmutable|null}
     */
    public function filters(): array
    {
        /** @var Test $test */
        $test = $this->route('test');
        $ulid = $this->string('group')->toString();
        $from = $this->string('from')->toString();
        $to = $this->string('to')->toString();

        return [
            'group' => $ulid === '' ? null : $test->groups()->where('groups.ulid', $ulid)->first(),
            'from' => $from === '' ? null : CarbonImmutable::createFromFormat('Y-m-d', $from)?->startOfDay(),
            // Inclusive end date: everything before the next midnight.
            'to' => $to === '' ? null : CarbonImmutable::createFromFormat('Y-m-d', $to)?->addDay()->startOfDay(),
        ];
    }
}
