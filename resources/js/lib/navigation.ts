import {
    BookOpenCheck,
    ChartNoAxesColumn,
    ClipboardCheck,
    ClipboardList,
    FileQuestion,
    FolderTree,
    GraduationCap,
    LayoutGrid,
    ScrollText,
    Tags,
    UserCheck,
    Users,
    UsersRound,
} from 'lucide-react';
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import student from '@/routes/student';
import teacher from '@/routes/teacher';
import type { NavGroup, Permission } from '@/types';

/**
 * Sidebar navigation. Every item declares the permission it needs; items the
 * user lacks are not rendered (cosmetic only: each route enforces its permission).
 */
export const navigation: NavGroup[] = [
    {
        title: 'Platform',
        items: [{ title: 'Dashboard', href: dashboard(), icon: LayoutGrid }],
    },
    {
        title: 'Teaching',
        items: [
            {
                title: 'Question bank',
                href: teacher.questions.index(),
                icon: FileQuestion,
                permission: 'questions.view-own',
            },
            {
                title: 'Tests',
                href: teacher.tests.index(),
                icon: ClipboardList,
                permission: 'tests.view-own',
            },
            {
                title: 'Groups',
                href: teacher.groups.index(),
                icon: UsersRound,
                permission: 'groups.manage-own',
            },
            {
                title: 'Grading',
                href: teacher.grading.index(),
                icon: ClipboardCheck,
                permission: 'attempts.grade-own-tests',
            },
        ],
    },
    {
        title: 'Learning',
        items: [
            {
                title: 'My groups',
                href: student.groups.index(),
                icon: UsersRound,
                permission: 'groups.join',
            },
            {
                title: 'My exams',
                href: student.exams.index(),
                icon: BookOpenCheck,
                permission: 'exams.take',
            },
            {
                title: 'Results',
                href: student.results.index(),
                icon: ChartNoAxesColumn,
                permission: 'results.view-own',
            },
            {
                title: 'Become a teacher',
                href: student.teacherRequest.show(),
                icon: GraduationCap,
                permission: 'teacher-request.create',
            },
        ],
    },
    {
        title: 'Administration',
        items: [
            {
                title: 'Users',
                href: admin.users.index(),
                icon: Users,
                permission: 'users.view',
            },
            {
                title: 'Teacher requests',
                href: admin.teacherRequests.index(),
                icon: UserCheck,
                permission: 'users.manage-roles',
                badge: 'teacherRequests',
            },
            {
                title: 'Categories',
                href: admin.categories.index(),
                icon: FolderTree,
                permission: 'categories.manage',
            },
            {
                title: 'Tags',
                href: admin.tags.index(),
                icon: Tags,
                permission: 'tags.manage',
            },
            {
                title: 'Questions',
                href: admin.questions.index(),
                icon: FileQuestion,
                permission: 'questions.view-any',
            },
            {
                title: 'Audit log',
                href: admin.auditLog.index(),
                icon: ScrollText,
                permission: 'audit-log.view',
            },
        ],
    },
];

/**
 * Keep only the items the user may see; drop groups that end up empty.
 */
export function visibleNavigation(
    groups: NavGroup[],
    can: (permission: Permission) => boolean,
): NavGroup[] {
    return groups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => item.permission === undefined || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);
}
