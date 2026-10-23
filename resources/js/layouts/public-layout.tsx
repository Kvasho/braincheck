import { Link, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { LocaleSwitcher } from '@/components/locale-switcher';
import { Logo } from '@/components/logo';
import { SynapseBackground } from '@/components/synapse-background';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, home, login, register } from '@/routes';

/**
 * Layout for public pages: landing page and certificate verification.
 */
export default function PublicLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <div className="relative isolate flex min-h-svh flex-col overflow-hidden bg-background text-foreground">
            <SynapseBackground />

            <header className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-6">
                <Link
                    href={home()}
                    className="rounded-md focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Logo />
                </Link>

                <nav className="flex items-center gap-2">
                    <LocaleSwitcher />
                    {auth.user ? (
                        <Button asChild>
                            <Link href={dashboard()}>{t('Dashboard')}</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild variant="ghost">
                                <Link href={login()}>{t('Log in')}</Link>
                            </Button>
                            <Button asChild>
                                <Link href={register()}>{t('Register')}</Link>
                            </Button>
                        </>
                    )}
                </nav>
            </header>

            <main className="mx-auto flex w-full max-w-6xl flex-1 flex-col px-6">
                {children}
            </main>

            <footer className="mx-auto w-full max-w-6xl px-6 py-8 text-sm text-muted-foreground">
                © {new Date().getFullYear()} BrainCheck
            </footer>
        </div>
    );
}
