import { Archive, CircleCheck, PencilLine } from 'lucide-react';
import { StatusBadge } from '@/components/data/status-badge';
import { useTranslation } from '@/hooks/use-translation';
import type { TestStatus } from '@/types';

/** Draft / published / archived, with icon and text. */
export function TestStatusBadge({ status }: { status: TestStatus }) {
    const { t } = useTranslation();

    switch (status) {
        case 'published':
            return (
                <StatusBadge
                    tone="success"
                    icon={CircleCheck}
                    label={t('Published')}
                />
            );
        case 'archived':
            return (
                <StatusBadge
                    tone="neutral"
                    icon={Archive}
                    label={t('Archived')}
                />
            );
        default:
            return (
                <StatusBadge tone="info" icon={PencilLine} label={t('Draft')} />
            );
    }
}
