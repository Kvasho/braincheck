<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Granular permissions, named `area.action`.
 *
 * Code checks permissions and policies, never role names. The role → permission
 * mapping lives in Role::permissions() (single source of truth).
 */
enum Permission: string
{
    // Users
    case UsersView = 'users.view';
    case UsersManageRoles = 'users.manage-roles';
    case UsersBlock = 'users.block';

    // Taxonomy
    case CategoriesManage = 'categories.manage';
    case TagsManage = 'tags.manage';

    // Audit
    case AuditLogView = 'audit-log.view';

    // Questions
    case QuestionsViewOwn = 'questions.view-own';
    case QuestionsManageOwn = 'questions.manage-own';
    case QuestionsViewAny = 'questions.view-any';

    // Tests
    case TestsViewOwn = 'tests.view-own';
    case TestsManageOwn = 'tests.manage-own';
    case TestsViewAny = 'tests.view-any';

    // Groups
    case GroupsManageOwn = 'groups.manage-own';
    case GroupsViewAny = 'groups.view-any';
    case GroupsJoin = 'groups.join';

    // Teacher access requests (student side; admins review with users.manage-roles)
    case TeacherRequestCreate = 'teacher-request.create';

    // Grading
    case AttemptsGradeOwnTests = 'attempts.grade-own-tests';
    /** Regrade a question of any test (admin; owners regrade with grade-own-tests). */
    case AttemptsRegradeAny = 'attempts.regrade-any';

    // Analytics
    case AnalyticsViewOwnTests = 'analytics.view-own-tests';
    case AnalyticsViewAny = 'analytics.view-any';

    // Exams
    case ExamsTake = 'exams.take';
    case ResultsViewOwn = 'results.view-own';

    // Certificates
    case CertificatesViewOwn = 'certificates.view-own';

    // Leaderboard
    case LeaderboardView = 'leaderboard.view';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }
}
