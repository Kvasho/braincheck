import { CircleCheck, CircleMinus, Clock } from 'lucide-react';
import { StatusBadge } from '@/components/data/status-badge';
import { useTranslation } from '@/hooks/use-translation';

/** Whether students can join right now: open, turned off, or code expired. */
export function JoinStatusBadge({
    joiningEnabled,
    acceptsJoins,
}: {
    joiningEnabled: boolean;
    acceptsJoins: boolean;
}) {
    const { t } = useTranslation();

    if (acceptsJoins) {
        return (
            <StatusBadge
                tone="success"
                icon={CircleCheck}
                label={t('Joining open')}
            />
        );
    }

    return joiningEnabled ? (
        <StatusBadge tone="warning" icon={Clock} label={t('Code expired')} />
    ) : (
        <StatusBadge
            tone="neutral"
            icon={CircleMinus}
            label={t('Joining off')}
        />
    );
}
