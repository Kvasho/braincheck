<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Attempt;
use App\Models\Test;
use App\Models\User;

/**
 * Attempt policy: deny by default.
 *
 * Denied by default; only the rules below are defined so far. Everything else is
 * added (and tested) by the stage that implements it.
 */
final class AttemptPolicy
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
    public function view(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id && $user->can(Permission::ResultsViewOwn->value);
    }

    /**
     * Take the attempt (player, autosave, events, submit): the owner of a real
     * attempt. Whether it is still in progress is checked by the Actions. Not
     * tied to group membership: a removed student may finish an attempt
     * already in progress.
     */
    public function take(User $user, Attempt $attempt): bool
    {
        return $attempt->user_id === $user->id
            && ! $attempt->is_preview
            && $user->can(Permission::ExamsTake->value);
    }

    /**
     * Grade, override and reveal the student of a real attempt: the owner of
     * its test, with the grading permission.
     */
    public function grade(User $user, Attempt $attempt): bool
    {
        return ! $attempt->is_preview
            && $user->can(Permission::AttemptsGradeOwnTests->value)
            && Test::query()->withTrashed()->whereKey($attempt->test_id)->where('owner_id', $user->id)->exists();
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
    public function update(User $user, Attempt $attempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Attempt $attempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Attempt $attempt): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Attempt $attempt): bool
    {
        return false;
    }
}
