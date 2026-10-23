<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\CreateCategory;
use App\Actions\Categories\DeleteCategory;
use App\Actions\Categories\UpdateCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Category::class);

        $tree = Category::query()
            ->whereNull('parent_id')
            ->withCount(['questions', 'tests'])
            ->with(['children' => fn (HasMany $query) => $query->withCount(['questions', 'tests'])])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/categories/index', [
            'tree' => CategoryResource::collection($tree)->resolve($request),
        ]);
    }

    public function store(StoreCategoryRequest $request, CreateCategory $createCategory): RedirectResponse
    {
        $createCategory->handle($request->localizedName(), $request->slug(), $request->parentCategory(), $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return to_route('admin.categories.index');
    }

    public function update(UpdateCategoryRequest $request, Category $category, UpdateCategory $updateCategory): RedirectResponse
    {
        $updateCategory->handle($category, $request->localizedName(), $request->slug(), $request->parentCategory(), $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category updated.')]);

        return to_route('admin.categories.index');
    }

    public function destroy(Request $request, Category $category, DeleteCategory $deleteCategory): RedirectResponse
    {
        Gate::authorize('delete', $category);

        $deleteCategory->handle($category, $request->user() ?? abort(403));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return to_route('admin.categories.index');
    }
}
