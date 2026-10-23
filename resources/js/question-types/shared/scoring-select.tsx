import InputError from '@/components/input-error';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTranslation } from '@/hooks/use-translation';
import type { MultipleChoiceScoring, Option } from '@/types';

/** all_or_nothing / partial (labels come from the server enum). */
export function ScoringSelect({
    value,
    options,
    onChange,
    error,
}: {
    value: MultipleChoiceScoring;
    options: Option[];
    onChange: (value: MultipleChoiceScoring) => void;
    error?: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid max-w-xs gap-2">
            <Label htmlFor="scoring">{t('Scoring')}</Label>
            <Select
                value={value}
                onValueChange={(next) =>
                    onChange(next as MultipleChoiceScoring)
                }
            >
                <SelectTrigger id="scoring">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {options.map((option) => (
                        <SelectItem key={option.value} value={option.value}>
                            {option.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <InputError message={error} />
        </div>
    );
}
