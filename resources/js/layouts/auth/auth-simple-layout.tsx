import { Link } from '@inertiajs/react';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { Logo } from '@/components/logo';
import { SynapseBackground } from '@/components/synapse-background';
import { useTranslation } from '@/hooks/use-translation';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { translate } = useTranslation();

    return (
        <div className="relative isolate flex min-h-svh flex-col items-center justify-center gap-6 overflow-hidden bg-background p-6 md:p-10">
            <SynapseBackground />
            <LocaleSwitcher className="absolute top-4 right-4" />
            <div className="w-full max-w-sm">
                <div className="flex flex-col gap-8 rounded-xl border bg-card/80 p-8 text-card-foreground shadow-sm backdrop-blur-sm">
                    <div className="flex flex-col items-center gap-4">
                        <Link
                            href={home()}
                            className="rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <Logo />
                        </Link>

                        <div className="space-y-2 text-center">
                            <h1 className="text-xl font-medium">
                                {translate(title ?? '')}
                            </h1>
                            <p className="text-center text-sm text-muted-foreground">
                                {translate(description ?? '')}
                            </p>
                        </div>
                    </div>
                    {children}
                </div>
            </div>
        </div>
    );
}
