<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

/**
 * Category policy. Admins with categories.manage maintain the tree; business
 * rules (depth 2, delete only without children, same-level reorder) live in the
 * Actions and Form Requests. Reading the tree for selects happens through the
 * cached CategoryTree, not through this policy.
 */
final class CategoryPolicy
{
    /**
     * Determine whether the user can list any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CategoriesManage->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can(Permission::CategoriesManage->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoriesManage->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoriesManage->value);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Category $category): bool
    {
        return false;
    }

    /**
     * Reorder siblings (drag & drop).
     */
    public function reorder(User $user, Category $category): bool
    {
        return $user->can(Permission::CategoriesManage->value);
    }
}
