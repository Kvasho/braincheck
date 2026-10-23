<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tags\MergeTags;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MergeTagRequest;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/**
 * Merge a tag into another one (the route's tag is deleted).
 */
final class TagMergeController extends Controller
{
    public function store(MergeTagRequest $request, Tag $tag, MergeTags $mergeTags): RedirectResponse
    {
        $target = $request->target();

        $mergeTags->handle($tag, $target, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('":source" was merged into ":target".', ['source' => $tag->name, 'target' => $target->name])]);

        return back();
    }
}
