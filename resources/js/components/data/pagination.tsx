import { ChevronLeft, ChevronRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import type { PaginationMeta } from '@/types';

/**
 * Previous / next navigation for Laravel's paginator (meta from Listing::toProps()).
 */
export function Pagination({
    meta,
    onPageChange,
}: {
    meta: PaginationMeta;
    onPageChange: (page: number) => void;
}) {
    const { t } = useTranslation();

    if (meta.total === 0) {
        return null;
    }

    return (
        <nav
            aria-label={t('Pagination')}
            className="flex flex-col items-center justify-between gap-3 text-sm sm:flex-row"
        >
            <p className="tabular text-muted-foreground" aria-live="polite">
                {t('Showing :from–:to of :total', {
                    from: meta.from ?? 0,
                    to: meta.to ?? 0,
                    total: meta.total,
                })}
            </p>

            <div className="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page <= 1}
                    onClick={() => onPageChange(meta.current_page - 1)}
                >
                    <ChevronLeft aria-hidden="true" />
                    {t('Previous')}
                </Button>
                <span className="tabular px-2">
                    {t('Page :page of :pages', {
                        page: meta.current_page,
                        pages: meta.last_page,
                    })}
                </span>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={meta.current_page >= meta.last_page}
                    onClick={() => onPageChange(meta.current_page + 1)}
                >
                    {t('Next')}
                    <ChevronRight aria-hidden="true" />
                </Button>
            </div>
        </nav>
    );
}
