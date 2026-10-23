<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AuditAction;
use App\Http\Requests\ListingRequest;
use App\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rule;

final class AuditLogIndexRequest extends ListingRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuditLog::class) === true;
    }

    protected function filterRules(): array
    {
        return [
            'action' => ['nullable', 'array', 'max:'.count(AuditAction::cases())],
            'action.*' => ['string', Rule::enum(AuditAction::class)],
            'actor' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'subject_type' => ['nullable', 'string', Rule::in(array_keys(Relation::morphMap()))],
        ];
    }

    /**
     * @return array{action: list<string>, actor: string|null, from: CarbonImmutable|null, to: CarbonImmutable|null, subject_type: string|null}
     */
    public function filters(): array
    {
        /** @var list<string> $actions */
        $actions = array_values(array_unique($this->validated('action') ?? []));
        $actor = trim($this->string('actor')->toString());

        return [
            'action' => $actions,
            'actor' => $actor === '' ? null : $actor,
            'from' => $this->filled('from') ? CarbonImmutable::createFromFormat('Y-m-d', $this->string('from')->toString())?->startOfDay() : null,
            'to' => $this->filled('to') ? CarbonImmutable::createFromFormat('Y-m-d', $this->string('to')->toString())?->endOfDay() : null,
            'subject_type' => $this->string('subject_type')->toString() ?: null,
        ];
    }
}
