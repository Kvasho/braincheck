import type { Permission, Role } from '@/types/authorization';

/** Mirrors App\Http\Resources\AuthUserResource: keep both in sync. */
export type User = {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    role: Role | null;
};

export type Auth = {
    user: User;
    /** UI hint only: the backend enforces every permission. */
    permissions: Permission[];
};

export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
