import type { AppLocale } from '@/hooks/use-translation';
import type { Auth } from '@/types/auth';
import type { NavBadge } from '@/types/navigation';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            locale: AppLocale;
            locales: { value: AppLocale; label: string }[];
            translations: Record<string, string>;
            flash: { status: string | null };
            sidebarOpen: boolean;
            navBadges: Partial<Record<NavBadge, number>>;
            [key: string]: unknown;
        };
    }
}
