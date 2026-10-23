<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tags\CreateTag;
use App\Actions\Tags\DeleteTag;
use App\Actions\Tags\UpdateTag;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTagRequest;
use App\Http\Requests\Admin\TagIndexRequest;
use App\Http\Requests\Admin\UpdateTagRequest;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Support\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TagController extends Controller
{
    /** Merge targets offered in the dialog (a select, not a search). */
    private const int MERGE_OPTIONS_LIMIT = 1000;

    public function index(TagIndexRequest $request): Response
    {
        $listing = new Listing(
            searchable: ['name', 'slug'],
            sortable: ['name' => 'name', 'questions_count' => 'questions_count', 'created_at' => 'created_at'],
            defaultSort: 'name',
            defaultDirection: 'asc',
        );

        $params = $request->listingParams();

        return Inertia::render('admin/tags/index', [
            'tags' => Listing::toProps($listing->paginate(Tag::query()->withCount('questions'), $params), TagResource::class, $request),
            'filters' => $listing->state($params),
            'mergeOptions' => Tag::query()
                ->orderBy('name')
                ->limit(self::MERGE_OPTIONS_LIMIT)
                ->get(['ulid', 'name'])
                ->map(fn (Tag $tag): array => ['value' => $tag->ulid, 'label' => $tag->name])
                ->all(),
        ]);
    }

    public function store(StoreTagRequest $request, CreateTag $createTag): RedirectResponse
    {
        $createTag->handle($request->name(), $request->slug(), $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag created.')]);

        return back();
    }

    public function update(UpdateTagRequest $request, Tag $tag, UpdateTag $updateTag): RedirectResponse
    {
        $updateTag->handle($tag, $request->name(), $request->slug(), $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag updated.')]);

        return back();
    }

    public function destroy(Request $request, Tag $tag, DeleteTag $deleteTag): RedirectResponse
    {
        Gate::authorize('delete', $tag);

        $deleteTag->handle($tag, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tag deleted.')]);

        return back();
    }
}
