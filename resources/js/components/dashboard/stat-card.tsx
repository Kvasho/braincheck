import { Link } from '@inertiajs/react';
import type { InertiaLinkProps } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';

/** One headline number (a stat tile, not a chart). Optionally a link. */
export function StatCard({
    label,
    value,
    href,
}: {
    label: string;
    value: string;
    href?: NonNullable<InertiaLinkProps['href']>;
}) {
    const body = (
        <Card className="h-full gap-1 py-4 transition-colors hover:bg-muted/30">
            <CardContent className="space-y-1 px-4">
                <p className="text-xs text-muted-foreground">{label}</p>
                <p className="tabular text-2xl font-semibold">{value}</p>
            </CardContent>
        </Card>
    );

    return href ? (
        <Link
            href={href}
            className="rounded-xl focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            {body}
        </Link>
    ) : (
        body
    );
}
