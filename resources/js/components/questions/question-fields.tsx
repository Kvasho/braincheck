import { ChevronDown } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import { questionTypes } from '@/question-types/registry';
import type {
    CategoryTreeNode,
    Difficulty,
    Option,
    QuestionType,
} from '@/types';

const NONE = '__none__';

export function QuestionTypeBadge({ type }: { type: QuestionType }) {
    const { t } = useTranslation();
    const { icon: Icon, label } = questionTypes[type];

    return (
        <Badge variant="outline">
            <Icon aria-hidden="true" />
            {t(label)}
        </Badge>
    );
}

export function DifficultyBadge({
    difficulty,
    label,
}: {
    difficulty: Difficulty;
    label: string;
}) {
    const variant =
        difficulty === 'easy'
            ? 'success'
            : difficulty === 'hard'
              ? 'warning'
              : 'secondary';

    return <Badge variant={variant}>{label}</Badge>;
}

/** Categories and subcategories (indented) in one select. */
export function CategorySelect({
    id,
    value,
    categories,
    onChange,
    allowAll = false,
}: {
    id: string;
    value: string | null;
    categories: CategoryTreeNode[];
    onChange: (value: string | null) => void;
    allowAll?: boolean;
}) {
    const { t, locale } = useTranslation();

    return (
        <Select
            value={value ?? NONE}
            onValueChange={(next) => onChange(next === NONE ? null : next)}
        >
            <SelectTrigger id={id}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={NONE}>
                    {allowAll ? t('All') : t('No category')}
                </SelectItem>
                {categories.flatMap((category) => [
                    <SelectItem key={category.ulid} value={category.ulid}>
                        {category.name[locale]}
                    </SelectItem>,
                    ...(category.children ?? []).map((child) => (
                        <SelectItem key={child.ulid} value={child.ulid}>
                            <span className="pl-4">— {child.name[locale]}</span>
                        </SelectItem>
                    )),
                ])}
            </SelectContent>
        </Select>
    );
}

/** Pick existing tags (tags themselves are managed by admins). */
export function TagPicker({
    id,
    value,
    options,
    onChange,
    placeholder,
}: {
    id: string;
    value: string[];
    options: Option[];
    onChange: (value: string[]) => void;
    placeholder?: string;
}) {
    const { t } = useTranslation();
    const selected = options.filter((option) => value.includes(option.value));

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className="h-auto min-h-9 w-full justify-between font-normal"
                >
                    <span className="flex flex-wrap gap-1 text-left">
                        {selected.length === 0 ? (
                            <span className="text-muted-foreground">
                                {placeholder ?? t('No tags')}
                            </span>
                        ) : (
                            selected.map((option) => (
                                <Badge key={option.value} variant="secondary">
                                    {option.label}
                                </Badge>
                            ))
                        )}
                    </span>
                    <ChevronDown className="opacity-50" aria-hidden="true" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="start"
                className="max-h-80 w-64 overflow-y-auto"
            >
                {options.length === 0 && (
                    <p className="p-2 text-sm text-muted-foreground">
                        {t('No tags yet. An administrator creates them.')}
                    </p>
                )}
                {options.map((option) => (
                    <DropdownMenuCheckboxItem
                        key={option.value}
                        checked={value.includes(option.value)}
                        onSelect={(event) => event.preventDefault()}
                        onCheckedChange={(checked) =>
                            onChange(
                                checked === true
                                    ? [...value, option.value]
                                    : value.filter(
                                          (tag) => tag !== option.value,
                                      ),
                            )
                        }
                    >
                        {option.label}
                    </DropdownMenuCheckboxItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
