import type { Option } from '@/types/admin';
import type { TestStatus } from '@/types/enums';

/** Mirrors App\Http\Resources\GroupResource (owner only). */
export type GroupRow = {
    ulid: string;
    name: string;
    members_count: number;
    tests_count: number;
    joining_enabled: boolean;
    accepts_joins: boolean;
    invite_expires_at: string | null;
    archived: boolean;
    created_at: string | null;
};

/** Mirrors App\Http\Resources\GroupDetailResource (no invite code). */
export type GroupDetail = {
    ulid: string;
    name: string;
    description: string | null;
    joining_enabled: boolean;
    accepts_joins: boolean;
    invite_expires_at: string | null;
    invite_expires_at_local: string | null;
    members_count: number;
    tests_count: number;
    created_at: string | null;
};

/** Mirrors App\Http\Resources\GroupMemberResource (owner only: has the email). */
export type GroupMember = {
    ulid: string;
    name: string;
    email: string;
    joined_at: string | null;
    attempts_count: number;
};

/** Mirrors App\Http\Resources\GroupTestResource. */
export type GroupTest = {
    ulid: string;
    title: string;
    status: TestStatus;
    opens_at: string | null;
    closes_at: string | null;
    assigned_at: string | null;
};

/** GroupInviteCodeController::show(). */
export type GroupInvite = {
    code: string;
    join_url: string;
    /** SVG data URI. */
    qr: string;
};

export type GroupActivity = {
    joins: { name: string; at: string | null }[];
    finished: { name: string; test: string; at: string | null }[];
};

/** GroupController::formOptions(). */
export type GroupFormOptions = {
    expiries: Option[];
    timezone: string;
    nameMax: number;
    descriptionMax: number;
};

/** Mirrors App\Http\Resources\StudentGroupResource: never other members. */
export type StudentGroup = {
    ulid: string;
    name: string;
    description: string | null;
    teacher: { name: string };
    tests_count: number;
};
