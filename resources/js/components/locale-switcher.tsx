import { router, usePage } from '@inertiajs/react';
import { Check, Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AppLocale } from '@/hooks/use-translation';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';

/**
 * Language switcher. Options come from the server (App\Enums\Locale::options()),
 * so the enum stays the single source of truth.
 */
export function LocaleSwitcher({ className }: { className?: string }) {
    const { locales } = usePage().props;
    const { t, locale } = useTranslation();

    const switchTo = (value: AppLocale): void => {
        if (value === locale) {
            return;
        }

        router.post(update.url(), { locale: value }, { preserveScroll: true });
    };

    const current = locales.find((option) => option.value === locale);

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="sm"
                    className={cn('gap-2', className)}
                    aria-label={t('Language')}
                    data-test="locale-switcher"
                >
                    <Languages className="size-4" aria-hidden="true" />
                    <span className="uppercase">
                        {current?.value ?? locale}
                    </span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {locales.map((option) => (
                    <DropdownMenuItem
                        key={option.value}
                        lang={option.value}
                        onSelect={() => switchTo(option.value)}
                    >
                        <Check
                            className={cn(
                                'size-4',
                                option.value === locale
                                    ? 'opacity-100'
                                    : 'opacity-0',
                            )}
                            aria-hidden="true"
                        />
                        {option.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
