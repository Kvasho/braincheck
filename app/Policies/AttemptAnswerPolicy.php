<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttemptAnswer;
use App\Models\User;

/**
 * AttemptAnswer policy: deny by default.
 *
 * Every ability is denied until the stage that implements it adds (and tests) a rule.
 */
final class AttemptAnswerPolicy
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
    public function view(User $user, AttemptAnswer $attemptAnswer): bool
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
    public function update(User $user, AttemptAnswer $attemptAnswer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, AttemptAnswer $attemptAnswer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, AttemptAnswer $attemptAnswer): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, AttemptAnswer $attemptAnswer): bool
    {
        return false;
    }
}
