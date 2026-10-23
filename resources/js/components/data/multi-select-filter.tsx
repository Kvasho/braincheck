import { ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';

/**
 * Multi-value filter for FilterBar (keyboard accessible via Radix DropdownMenu).
 */
export function MultiSelectFilter({
    id,
    label,
    values,
    options,
    onChange,
}: {
    id: string;
    /** Already translated label. */
    label: string;
    values: string[];
    options: { value: string; label: string }[];
    onChange: (values: string[]) => void;
}) {
    const { t } = useTranslation();

    const toggle = (value: string, checked: boolean) =>
        onChange(
            checked
                ? [...values, value]
                : values.filter((selected) => selected !== value),
        );

    return (
        <div className="grid gap-1.5 md:w-56">
            <Label htmlFor={id}>{label}</Label>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        id={id}
                        type="button"
                        variant="outline"
                        className="justify-between font-normal"
                    >
                        <span className="truncate">
                            {values.length === 0
                                ? t('All')
                                : t(':count selected', {
                                      count: values.length,
                                  })}
                        </span>
                        <ChevronDown
                            className="opacity-50"
                            aria-hidden="true"
                        />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="start"
                    className="max-h-80 w-64 overflow-y-auto"
                >
                    {options.map((option) => (
                        <DropdownMenuCheckboxItem
                            key={option.value}
                            checked={values.includes(option.value)}
                            onSelect={(event) => event.preventDefault()}
                            onCheckedChange={(checked) =>
                                toggle(option.value, checked === true)
                            }
                        >
                            {option.label}
                        </DropdownMenuCheckboxItem>
                    ))}
                    {values.length > 0 && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem onSelect={() => onChange([])}>
                                {t('Clear selection')}
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
