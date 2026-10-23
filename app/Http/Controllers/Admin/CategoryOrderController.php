<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Categories\ReorderCategories;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderCategoriesRequest;
use Illuminate\Http\RedirectResponse;

/**
 * The order of one category level (drag & drop).
 */
final class CategoryOrderController extends Controller
{
    public function update(ReorderCategoriesRequest $request, ReorderCategories $reorderCategories): RedirectResponse
    {
        $reorderCategories->handle($request->parentCategory(), $request->order(), $request->user() ?? abort(403));

        return to_route('admin.categories.index');
    }
}
