<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * The three roles. Every user has exactly one (see App\Actions\Users\ChangeUserRole).
 *
 * Authorization never checks role names: it checks permissions (granted through
 * Role::permissions()) and policies. Use the role only for dashboard selection
 * and display.
 */
enum Role: string implements HasLabel
{
    case Admin = 'admin';
    case Teacher = 'teacher';
    case Student = 'student';

    /**
     * Translated, human-readable name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Teacher => __('Teacher'),
            self::Student => __('Student'),
        };
    }

    /**
     * Value/label pairs for selects.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role): array => ['value' => $role->value, 'label' => $role->label()],
            self::cases(),
        );
    }

    /**
     * Roles an admin may assign in the web UI (never Admin; see braincheck:make-admin).
     *
     * @return list<array{value: string, label: string}>
     */
    public static function assignableOptions(): array
    {
        return array_values(array_filter(
            self::options(),
            fn (array $option): bool => $option['value'] !== self::Admin->value,
        ));
    }

    /**
     * Single source of truth for the role → permission mapping.
     *
     * @return array<value-of<Role>, list<Permission>>
     */
    public static function permissions(): array
    {
        $map = [];

        foreach (self::cases() as $role) {
            $map[$role->value] = $role->grantedPermissions();
        }

        return $map;
    }

    /**
     * Permissions granted to this role.
     *
     * Admins oversee the platform (users, taxonomy, audit, every `*-any` view) but do
     * not author content (`*-own`) and never take exams.
     *
     * @return list<Permission>
     */
    public function grantedPermissions(): array
    {
        return match ($this) {
            self::Admin => [
                Permission::UsersView,
                Permission::UsersManageRoles,
                Permission::UsersBlock,
                Permission::CategoriesManage,
                Permission::TagsManage,
                Permission::AuditLogView,
                Permission::QuestionsViewAny,
                Permission::TestsViewAny,
                Permission::GroupsViewAny,
                Permission::AttemptsRegradeAny,
                Permission::AnalyticsViewAny,
                Permission::LeaderboardView,
            ],
            self::Teacher => [
                Permission::QuestionsViewOwn,
                Permission::QuestionsManageOwn,
                Permission::TestsViewOwn,
                Permission::TestsManageOwn,
                Permission::GroupsManageOwn,
                Permission::AttemptsGradeOwnTests,
                Permission::AnalyticsViewOwnTests,
                Permission::LeaderboardView,
            ],
            self::Student => [
                Permission::GroupsJoin,
                Permission::TeacherRequestCreate,
                Permission::ExamsTake,
                Permission::ResultsViewOwn,
                Permission::CertificatesViewOwn,
                Permission::LeaderboardView,
            ],
        };
    }

    /**
     * @return list<string>
     */
    public function permissionNames(): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, $this->grantedPermissions());
    }
}
