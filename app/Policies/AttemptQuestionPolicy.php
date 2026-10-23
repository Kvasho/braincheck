<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttemptQuestion;
use App\Models\User;

/**
 * AttemptQuestion policy: deny by default.
 *
 * Every ability is denied until the stage that implements it adds (and tests) a rule.
 */
final class AttemptQuestionPolicy
{
    /**
     * Determine whether the user can list any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, AttemptQuestion $attemptQuestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, AttemptQuestion $attemptQuestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AttemptQuestion $attemptQuestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AttemptQuestion $attemptQuestion): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AttemptQuestion $attemptQuestion): bool
    {
        return false;
    }
}
