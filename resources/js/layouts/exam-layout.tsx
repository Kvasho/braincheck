import type { ReactNode } from 'react';
import { Logo } from '@/components/logo';

/**
 * Distraction-free layout for taking an exam: no sidebar, no navigation,
 * no background animation. The header slot holds the timer (and similar
 * status such as progress), the logo is static and not a link.
 */
export default function ExamLayout({
    header,
    children,
}: {
    header?: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="sticky top-0 z-10 border-b bg-background">
                <div className="mx-auto flex h-14 w-full max-w-4xl items-center justify-between gap-4 px-4">
                    <Logo showName={false} />
                    <div className="flex items-center gap-4">{header}</div>
                </div>
            </header>
            <main className="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
                {children}
            </main>
        </div>
    );
}
