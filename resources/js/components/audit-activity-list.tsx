import { Link } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { EmptyState } from '@/components/data/empty-state';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useTranslation } from '@/hooks/use-translation';
import admin from '@/routes/admin';
import type { AuditEntry } from '@/types';

/**
 * Compact list of audit entries (user page, admin dashboard). All values are
 * rendered as text: React escapes them, nothing is injected as HTML.
 */
export function AuditActivityList({ entries }: { entries: AuditEntry[] }) {
    const { t, formatDate } = useTranslation();

    if (entries.length === 0) {
        return (
            <EmptyState
                icon={Activity}
                title={t('No activity yet')}
                description={t('Security-relevant events will appear here.')}
            />
        );
    }

    return (
        <ol className="divide-y rounded-xl border">
            {entries.map((entry, index) => (
                <li
                    key={`${entry.created_at}-${index}`}
                    className="flex flex-col gap-1 p-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                >
                    <div className="min-w-0">
                        <p className="font-medium">{entry.action_label}</p>
                        <p className="truncate text-muted-foreground">
                            {entry.actor ? (
                                <Link
                                    href={admin.users.show(entry.actor.ulid)}
                                    className="underline-offset-4 hover:underline"
                                >
                                    {entry.actor.name}
                                </Link>
                            ) : (
                                t('System')
                            )}
                            {entry.subject?.label && (
                                <> → {entry.subject.label}</>
                            )}
                        </p>
                    </div>
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <time
                                dateTime={entry.created_at}
                                className="tabular shrink-0 text-muted-foreground"
                            >
                                {formatDate(entry.created_at, 'PP p')}
                            </time>
                        </TooltipTrigger>
                        <TooltipContent>
                            {formatDate(entry.created_at, 'PPpp')}
                        </TooltipContent>
                    </Tooltip>
                </li>
            ))}
        </ol>
    );
}
