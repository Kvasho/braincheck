<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TeacherRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TeacherRequestIndexRequest;
use App\Http\Resources\TeacherAccessRequestResource;
use App\Models\TeacherAccessRequest;
use App\Support\Listing;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

final class TeacherRequestController extends Controller
{
    public function index(TeacherRequestIndexRequest $request): Response
    {
        $listing = new Listing(
            searchable: [],
            sortable: ['created_at' => 'created_at', 'reviewed_at' => 'reviewed_at'],
            defaultSort: 'created_at',
        );

        $params = $request->listingParams();
        $state = $listing->state($params);
        $status = $request->status();

        $query = TeacherAccessRequest::query()
            ->with(['user', 'reviewer'])
            ->when($status, fn (Builder $query, TeacherRequestStatus $status) => $query->where('status', $status->value))
            ->when($state['search'], fn (Builder $query, string $search) => $query->whereHas('user', function (Builder $query) use ($search): void {
                $term = '%'.Listing::escapeLike($search).'%';
                $query->where('name', 'like', $term)->orWhere('email', 'like', $term);
            }))
            // Pending first, then the chosen sort (constant expression, no user input).
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END");

        return Inertia::render('admin/teacher-requests/index', [
            'requests' => Listing::toProps($listing->paginate($query, $params), TeacherAccessRequestResource::class, $request),
            'filters' => [...$state, 'status' => $status?->value],
            'statuses' => array_map(
                fn (TeacherRequestStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                TeacherRequestStatus::cases(),
            ),
        ]);
    }
}
