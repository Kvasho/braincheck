/**
 * Mirrors App\Enums\Role and App\Enums\Permission.
 * tests/Arch/EnumParityTest.php fails if these drift from the PHP enums.
 */

export const ROLES = ['admin', 'teacher', 'student'] as const;

export type Role = (typeof ROLES)[number];

export const PERMISSIONS = [
    'users.view',
    'users.manage-roles',
    'users.block',
    'categories.manage',
    'tags.manage',
    'audit-log.view',
    'questions.view-own',
    'questions.manage-own',
    'questions.view-any',
    'tests.view-own',
    'tests.manage-own',
    'tests.view-any',
    'groups.manage-own',
    'groups.view-any',
    'groups.join',
    'teacher-request.create',
    'attempts.grade-own-tests',
    'attempts.regrade-any',
    'analytics.view-own-tests',
    'analytics.view-any',
    'exams.take',
    'results.view-own',
    'certificates.view-own',
    'leaderboard.view',
] as const;

export type Permission = (typeof PERMISSIONS)[number];
