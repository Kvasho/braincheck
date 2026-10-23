import { Head, Link, usePage } from '@inertiajs/react';
import { Award, BrainCircuit, ChartNoAxesColumn, Timer } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { TranslationKey } from '@/hooks/use-translation';
import { useTranslation } from '@/hooks/use-translation';
import { dashboard, register } from '@/routes';

const features: {
    icon: typeof Award;
    title: TranslationKey;
    description: TranslationKey;
}[] = [
    {
        icon: BrainCircuit,
        title: 'Question bank',
        description:
            'Build tests from reusable questions of many types and assign them to groups.',
    },
    {
        icon: Timer,
        title: 'Timed exams',
        description: 'Focused, distraction-free exams with automatic grading.',
    },
    {
        icon: ChartNoAxesColumn,
        title: 'Analytics',
        description: 'See how every group and every question performs.',
    },
    {
        icon: Award,
        title: 'Certificates & XP',
        description:
            'Verifiable certificates, badges and a leaderboard to keep learners going.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    // "[word]" marks the highlighted word, so each language can place it naturally.
    const [before = '', highlighted = '', after = ''] = t(
        'Exams that make every [connection] count',
    ).split(/\[|\]/);

    return (
        <>
            <Head title={t('Welcome')} />

            <section className="flex flex-col items-center gap-6 py-20 text-center md:py-28">
                <span className="rounded-full border bg-card/60 px-3 py-1 text-xs text-muted-foreground backdrop-blur-sm">
                    {t('Every correct answer lights a neuron')}
                </span>
                <h1 className="max-w-3xl text-4xl font-semibold tracking-tight text-balance md:text-6xl">
                    {before}
                    <span className="text-primary">{highlighted}</span>
                    {after}
                </h1>
                <p className="max-w-xl text-lg text-pretty text-muted-foreground">
                    {t(
                        'BrainCheck helps teachers build, assign and grade tests, and helps students see their progress grow.',
                    )}
                </p>
                <div className="flex flex-wrap justify-center gap-3">
                    {auth.user ? (
                        <Button asChild size="lg">
                            <Link href={dashboard()}>
                                {t('Go to dashboard')}
                            </Link>
                        </Button>
                    ) : (
                        <Button asChild size="lg">
                            <Link href={register()}>{t('Get started')}</Link>
                        </Button>
                    )}
                </div>
            </section>

            <section className="grid gap-4 pb-16 sm:grid-cols-2 lg:grid-cols-4">
                {features.map(({ icon: Icon, title, description }) => (
                    <Card key={title} className="bg-card/80 backdrop-blur-sm">
                        <CardHeader>
                            <Icon
                                className="size-6 text-primary"
                                aria-hidden="true"
                            />
                            <CardTitle className="mt-2">{t(title)}</CardTitle>
                            <CardDescription>{t(description)}</CardDescription>
                        </CardHeader>
                    </Card>
                ))}
            </section>
        </>
    );
}
