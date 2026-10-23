import { Head, Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard } from '@/routes';

/**
 * Placeholder for sections built in later stages. `section` is a translation key.
 */
export default function ComingSoon({ section }: { section: string }) {
    const { t, translate } = useTranslation();

    return (
        <>
            <Head title={translate(section)} />

            <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                <div className="flex size-14 items-center justify-center rounded-2xl bg-accent">
                    <Sparkles
                        className="size-7 text-primary"
                        aria-hidden="true"
                    />
                </div>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {translate(section)}
                </h1>
                <p className="text-sm font-medium text-primary">
                    {t('Coming soon')}
                </p>
                <p className="max-w-md text-muted-foreground">
                    {t(
                        'This section is part of a later stage. The navigation and access rules are already in place.',
                    )}
                </p>
                <Button asChild variant="outline">
                    <Link href={dashboard()}>{t('Back to dashboard')}</Link>
                </Button>
            </div>
        </>
    );
}
