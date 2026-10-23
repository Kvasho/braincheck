import { Badge } from '@/components/ui/badge';
import type { TranslationKey } from '@/hooks/use-translation';
import { useTranslation } from '@/hooks/use-translation';
import type { Role } from '@/types';

const labels: Record<Role, TranslationKey> = {
    admin: 'Admin',
    teacher: 'Teacher',
    student: 'Student',
};

const variants: Record<Role, 'default' | 'secondary' | 'success'> = {
    admin: 'default',
    teacher: 'secondary',
    student: 'success',
};

/** Displays the user's role (display only, never used for access decisions). */
export function RoleBadge({ role }: { role: Role | null }) {
    const { t } = useTranslation();

    if (role === null) {
        return null;
    }

    return (
        <Badge variant={variants[role]} data-test="role-badge">
            {t(labels[role])}
        </Badge>
    );
}
