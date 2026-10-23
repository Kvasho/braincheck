import type { SVGAttributes } from 'react';
import { cn } from '@/lib/utils';

/**
 * BrainCheck mark: three connected nodes ("neurons") forming a check.
 * Uses currentColor for the links and the primary token for the lit nodes.
 */
export function LogoMark({ className, ...props }: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 32 32"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            className={cn('text-foreground', className)}
            {...props}
        >
            <path
                d="M6 17 L13 24 L26 8"
                stroke="currentColor"
                strokeWidth={2.5}
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            <circle cx={6} cy={17} r={3.5} className="fill-primary" />
            <circle cx={13} cy={24} r={3.5} className="fill-primary" />
            <circle cx={26} cy={8} r={4} className="fill-primary" />
        </svg>
    );
}

/**
 * BrainCheck wordmark: mark + name. The name is real text (accessible, selectable)
 * set in the UI font, so it renders identically to the rest of the interface.
 */
export function Logo({
    className,
    showName = true,
}: {
    className?: string;
    showName?: boolean;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-2 text-foreground',
                className,
            )}
        >
            <LogoMark className="size-7 shrink-0" aria-hidden="true" />
            {showName ? (
                <span className="text-lg leading-none font-semibold tracking-tight">
                    Brain<span className="text-primary">Check</span>
                </span>
            ) : (
                <span className="sr-only">BrainCheck</span>
            )}
        </span>
    );
}
