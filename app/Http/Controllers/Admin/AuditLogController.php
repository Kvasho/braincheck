<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Support\Listing;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Read-only audit log viewer. There is deliberately no route that updates or
 * deletes entries (and the model/query builder would refuse anyway).
 */
final class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request): Response
    {
        // Newest first by primary key: append-only, so id order is time order
        // and uses the PK index on this large table.
        $listing = new Listing(searchable: [], sortable: ['created_at' => 'id'], defaultSort: 'created_at');

        $filters = $request->filters();
        $params = $request->listingParams();

        $query = AuditLog::query()
            ->with(['actor', 'subject'])
            ->when($filters['action'] !== [], fn (Builder $query) => $query->whereIn('action', $filters['action']))
            ->when($filters['actor'], fn (Builder $query, string $actor) => $query->whereHas('actor', function (Builder $query) use ($actor): void {
                $term = '%'.Listing::escapeLike($actor).'%';
                $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
            }))
            ->when($filters['from'], fn (Builder $query, $from) => $query->where('created_at', '>=', $from))
            ->when($filters['to'], fn (Builder $query, $to) => $query->where('created_at', '<=', $to))
            ->when($filters['subject_type'], fn (Builder $query, string $type) => $query->where('subject_type', $type));

        return Inertia::render('admin/audit-log/index', [
            'entries' => Listing::toProps($listing->paginate($query, $params), AuditLogResource::class, $request),
            'filters' => [
                ...$listing->state($params),
                'action' => $filters['action'],
                'actor' => $filters['actor'],
                'from' => $filters['from']?->toDateString(),
                'to' => $filters['to']?->toDateString(),
                'subject_type' => $filters['subject_type'],
            ],
            'actions' => array_map(
                fn (AuditAction $action): array => ['value' => $action->value, 'label' => $action->label()],
                AuditAction::cases(),
            ),
            'subjectTypes' => array_map(
                fn (string $type): array => ['value' => $type, 'label' => AuditLogResource::subjectTypeLabel($type)],
                ['user', 'category', 'tag', 'teacher_access_request'],
            ),
        ]);
    }
}
