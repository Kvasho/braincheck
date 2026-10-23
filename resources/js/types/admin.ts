import type { Role } from '@/types/authorization';
import type { TeacherRequestStatus } from '@/types/enums';

export type Option<T extends string = string> = { value: T; label: string };

/** Mirrors App\Http\Resources\UserResource. */
export type AdminUser = {
    ulid: string;
    name: string;
    email: string;
    role: Role | null;
    verified: boolean;
    blocked: boolean;
    has_two_factor: boolean;
    xp_total: number;
    created_at: string | null;
};

/** Mirrors App\Http\Resources\UserDetailResource. */
export type AdminUserDetail = AdminUser & {
    email_verified_at: string | null;
    blocked_at: string | null;
    blocked_reason: string | null;
    counts: {
        questions: number;
        tests: number;
        groups: number;
        attempts: number;
    };
};

/** Mirrors App\Http\Resources\AuditLogResource. */
export type AuditEntry = {
    /** An AuditAction value (older entries may hold retired keys). */
    action: string;
    action_label: string;
    actor: { ulid: string; name: string; email: string } | null;
    subject: {
        type: string;
        type_label: string;
        label: string | null;
        ulid: string | null;
    } | null;
    properties: Record<string, unknown> | unknown[];
    ip_address: string | null;
    user_agent: string | null;
    created_at: string;
};

/** Mirrors App\Http\Resources\TeacherAccessRequestResource. */
export type TeacherAccessRequest = {
    ulid: string;
    message: string;
    status: TeacherRequestStatus;
    review_note: string | null;
    reviewed_at: string | null;
    created_at: string | null;
    user: { ulid: string; name: string; email: string } | null;
    reviewer: { ulid: string; name: string } | null;
};

export type LocalizedName = { ka: string; en: string };

/** Mirrors App\Http\Resources\CategoryResource (tree node). */
export type CategoryNode = {
    ulid: string;
    name: LocalizedName;
    slug: string;
    sort_order: number;
    questions_count: number;
    tests_count: number;
    children: CategoryNode[];
};

/** Mirrors App\Http\Resources\TagResource. */
export type TagRow = {
    ulid: string;
    name: string;
    slug: string;
    questions_count: number;
    created_at: string | null;
};
