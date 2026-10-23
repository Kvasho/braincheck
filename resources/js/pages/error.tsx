import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import type { TranslationKey } from '@/hooks/use-translation';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, home } from '@/routes';

type Message = { title: TranslationKey; description: TranslationKey };

const fallback: Message = {
    title: 'Something went wrong',
    description: 'An unexpected error occurred. Please try again later.',
};

const messages: Record<number, Message> = {
    403: {
        title: 'Access denied',
        description: "You don't have permission to view this page.",
    },
    404: {
        title: 'Page not found',
        description:
            'The page you are looking for does not exist or has moved.',
    },
    419: {
        title: 'Page expired',
        description:
            'Your session expired. Please refresh the page and try again.',
    },
    429: {
        title: 'Too many requests',
        description: 'Please slow down and try again in a moment.',
    },
    500: fallback,
    503: {
        title: 'Service unavailable',
        description: 'We are doing some maintenance. Please check back soon.',
    },
};

/**
 * Styled error page (rendered from bootstrap/app.php). Never shows exception
 * details: only the status code and a translated, generic message.
 */
export default function ErrorPage({ status }: { status: number }) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const message = messages[status] ?? fallback;

    return (
        <>
            <Head title={t(message.title)} />

            <section className="flex flex-1 flex-col items-center justify-center gap-4 py-24 text-center">
                <p className="tabular text-6xl font-semibold text-primary">
                    {status}
                </p>
                <h1 className="text-2xl font-semibold tracking-tight">
                    {t(message.title)}
                </h1>
                <p className="max-w-md text-muted-foreground">
                    {t(message.description)}
                </p>
                <Button asChild>
                    {auth.user ? (
                        <Link href={dashboard()}>{t('Back to dashboard')}</Link>
                    ) : (
                        <Link href={home()}>{t('Go home')}</Link>
                    )}
                </Button>
            </section>
        </>
    );
}
