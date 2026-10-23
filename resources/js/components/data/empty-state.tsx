import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';
import type { ReactNode } from 'react';

export function EmptyState({
    icon: Icon = Inbox,
    title,
    description,
    action,
}: {
    icon?: LucideIcon;
    /** Already translated texts. */
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-10 text-center">
            <div className="flex size-12 items-center justify-center rounded-2xl bg-accent">
                <Icon className="size-6 text-primary" aria-hidden="true" />
            </div>
            <h2 className="font-medium">{title}</h2>
            {description && (
                <p className="max-w-sm text-sm text-muted-foreground">
                    {description}
                </p>
            )}
            {action}
        </div>
    );
}
