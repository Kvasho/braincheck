<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Question;
use App\Models\User;

/**
 * Question policy: owner only, plus admin read-only oversight.
 *
 * - Teachers (questions.view-own / questions.manage-own) act only on their own
 *   questions. Their listing is scoped to them, not authorized via viewAny.
 * - Admins (questions.view-any) may list, view, preview and see images of any
 *   question, never change one.
 * - Students: everything denied (exam access is Stage 7, through snapshots).
 */
final class QuestionPolicy
{
    /**
     * List every teacher's questions (admin moderation).
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::QuestionsViewAny->value);
    }

    public function view(User $user, Question $question): bool
    {
        return $user->can(Permission::QuestionsViewAny->value) || $this->ownsAnd($user, $question, Permission::QuestionsViewOwn);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::QuestionsManageOwn->value);
    }

    public function update(User $user, Question $question): bool
    {
        return ! $question->trashed() && $this->ownsAnd($user, $question, Permission::QuestionsManageOwn);
    }

    /**
     * Archive (soft delete).
     */
    public function delete(User $user, Question $question): bool
    {
        return ! $question->trashed() && $this->ownsAnd($user, $question, Permission::QuestionsManageOwn);
    }

    public function restore(User $user, Question $question): bool
    {
        return $question->trashed() && $this->ownsAnd($user, $question, Permission::QuestionsManageOwn);
    }

    /**
     * Questions are never hard-deleted: history (tests, attempts) references them.
     */
    public function forceDelete(User $user, Question $question): bool
    {
        return false;
    }

    public function duplicate(User $user, Question $question): bool
    {
        return $this->ownsAnd($user, $question, Permission::QuestionsManageOwn);
    }

    /**
     * See the question as a student would. Grading a preview answer reveals the
     * answer, so only the owner may do that (see previewGrade).
     */
    public function preview(User $user, Question $question): bool
    {
        return $this->view($user, $question);
    }

    public function previewGrade(User $user, Question $question): bool
    {
        return $this->ownsAnd($user, $question, Permission::QuestionsViewOwn);
    }

    /**
     * The image: owner and admins. Students get access through their attempts
     * in Stage 7 (denied until then).
     */
    public function viewImage(User $user, Question $question): bool
    {
        return $this->view($user, $question);
    }

    private function ownsAnd(User $user, Question $question, Permission $permission): bool
    {
        return $question->owner_id === $user->id && $user->can($permission->value);
    }
}
