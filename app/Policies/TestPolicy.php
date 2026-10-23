<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TestStatus;
use App\Models\Test;
use App\Models\User;

/**
 * Test policy: owner only, plus admin read-only oversight (tests.view-any).
 *
 * Status rules (draft → published → archived, unpublish while not locked) and
 * the lock rules live in the Actions; the policy decides who may try.
 * Archived tests are read-only.
 */
final class TestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::TestsViewAny->value);
    }

    public function view(User $user, Test $test): bool
    {
        return $user->can(Permission::TestsViewAny->value) || $this->ownsAnd($user, $test, Permission::TestsViewOwn);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::TestsManageOwn->value);
    }

    /**
     * Drafts and published tests (a locked test only accepts the allowed changes).
     */
    public function update(User $user, Test $test): bool
    {
        return $this->editable($test) && $this->ownsAnd($user, $test, Permission::TestsManageOwn);
    }

    /**
     * Archive.
     */
    public function delete(User $user, Test $test): bool
    {
        return $this->editable($test) && $this->ownsAnd($user, $test, Permission::TestsManageOwn);
    }

    /**
     * Fix a question's correct answer in this test's attempt snapshots and
     * regrade: the owner with the grading permission, or an admin.
     */
    public function regrade(User $user, Test $test): bool
    {
        return $user->can(Permission::AttemptsRegradeAny->value)
            || ($test->owner_id === $user->id && $user->can(Permission::AttemptsGradeOwnTests->value));
    }

    /**
     * Release the results of a `manual` test (owner; can't be undone).
     */
    public function releaseResults(User $user, Test $test): bool
    {
        return $test->owner_id === $user->id && $user->can(Permission::TestsManageOwn->value);
    }

    /**
     * Test analytics, attempt details and the results export: the owner, or
     * anyone with platform-wide analytics (admins).
     */
    public function viewAnalytics(User $user, Test $test): bool
    {
        return $user->can(Permission::AnalyticsViewAny->value)
            || ($test->owner_id === $user->id && $user->can(Permission::AnalyticsViewOwnTests->value));
    }

    public function restore(User $user, Test $test): bool
    {
        return false;
    }

    /**
     * Tests are never hard-deleted: attempts reference them.
     */
    public function forceDelete(User $user, Test $test): bool
    {
        return false;
    }

    public function publish(User $user, Test $test): bool
    {
        return $this->editable($test) && $this->ownsAnd($user, $test, Permission::TestsManageOwn);
    }

    public function duplicate(User $user, Test $test): bool
    {
        return $this->ownsAnd($user, $test, Permission::TestsManageOwn);
    }

    /**
     * Start a "preview as student" attempt.
     */
    public function preview(User $user, Test $test): bool
    {
        return $this->ownsAnd($user, $test, Permission::TestsViewOwn);
    }

    private function editable(Test $test): bool
    {
        return $test->status !== TestStatus::Archived && ! $test->trashed();
    }

    private function ownsAnd(User $user, Test $test, Permission $permission): bool
    {
        return $test->owner_id === $user->id && $user->can($permission->value);
    }
}
