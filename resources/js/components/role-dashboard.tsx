import { Head, Link, usePage } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { ArrowRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { RoleBadge } from '@/components/role-badge';
import {
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import type { TranslationKey } from '@/hooks/use-translation';
import { useTranslation } from '@/hooks/use-translation';

export type DashboardSection = {
    title: TranslationKey;
    description: TranslationKey;
    href: NonNullable<InertiaLinkProps['href']>;
    icon: LucideIcon;
    /** Footer hint; defaults to "Nothing here yet". */
    hint?: TranslationKey;
};

/**
 * Shared shell for the role dashboards: welcome text and empty-state cards.
 * Sections are static links; role data comes only from the page's own props
 * (built per role on the server), so nothing can leak between roles.
 */
export function RoleDashboard({
    title,
    intro,
    sections,
    children,
}: {
    title: TranslationKey;
    intro: TranslationKey;
    sections: DashboardSection[];
    /** Live data of the role (cards, lists), shown above the section links. */
    children?: ReactNode;
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();

    return (
        <>
            <Head title={t(title)} />

            <div className="flex flex-1 flex-col gap-8 p-4 md:p-6">
                <header className="space-y-2">
                    <RoleBadge role={auth.user.role} />
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('Welcome back, :name', { name: auth.user.name })}
                    </h1>
                    <p className="text-muted-foreground">{t(intro)}</p>
                </header>

                {children}

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    {sections.map(
                        ({
                            title: sectionTitle,
                            description,
                            href,
                            icon: Icon,
                            hint = 'Nothing here yet',
                        }) => (
                            <Card
                                key={sectionTitle}
                                className="justify-between"
                            >
                                <CardHeader>
                                    <div className="mb-2 flex size-10 items-center justify-center rounded-lg bg-accent">
                                        <Icon
                                            className="size-5 text-primary"
                                            aria-hidden="true"
                                        />
                                    </div>
                                    <CardTitle>{t(sectionTitle)}</CardTitle>
                                    <CardDescription>
                                        {t(description)}
                                    </CardDescription>
                                </CardHeader>
                                <CardFooter className="justify-between text-sm">
                                    <span className="text-muted-foreground">
                                        {t(hint)}
                                    </span>
                                    <Link
                                        href={href}
                                        className="inline-flex items-center gap-1 rounded-md font-medium text-primary focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        {t('Open')}
                                        <ArrowRight
                                            className="size-4"
                                            aria-hidden="true"
                                        />
                                    </Link>
                                </CardFooter>
                            </Card>
                        ),
                    )}
                </div>
            </div>
        </>
    );
}
