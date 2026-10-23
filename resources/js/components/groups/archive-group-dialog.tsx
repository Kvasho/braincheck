import { ConfirmDialog } from '@/components/data/confirm-dialog';
import { useTranslation } from '@/hooks/use-translation';

/** Confirm before archiving a group. */
export function ArchiveGroupDialog<T extends { ulid: string; name: string }>({
    group,
    processing,
    onClose,
    onConfirm,
}: {
    group: T | null;
    processing: boolean;
    onClose: () => void;
    onConfirm: (group: T) => void;
}) {
    const { t } = useTranslation();

    return (
        <ConfirmDialog
            open={group !== null}
            onOpenChange={(next) => !next && onClose()}
            title={t('Archive :name?', { name: group?.name ?? '' })}
            description={t(
                'Nobody can join any more and its students no longer see its tests. Results stay.',
            )}
            confirmLabel={t('Archive')}
            destructive
            processing={processing}
            onConfirm={() => group && onConfirm(group)}
        />
    );
}
