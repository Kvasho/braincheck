import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import type { PlayerProps } from '../shared/types';
import type { NumericAnswer, NumericPayload } from './types';

export default function NumericPlayer({
    payload,
    value,
    onChange,
    disabled,
    idPrefix,
}: PlayerProps<NumericPayload, NumericAnswer>) {
    const { t } = useTranslation();

    return (
        <div className="grid max-w-xs gap-2">
            <Label htmlFor={`${idPrefix}-value`}>{t('Your answer')}</Label>
            <div className="flex items-center gap-2">
                <Input
                    id={`${idPrefix}-value`}
                    inputMode="decimal"
                    autoComplete="off"
                    className="tabular"
                    maxLength={32}
                    disabled={disabled}
                    value={value.value ?? ''}
                    onChange={(event) =>
                        onChange({ value: event.target.value || null })
                    }
                />
                {payload.unit && (
                    <span className="text-muted-foreground">
                        {payload.unit}
                    </span>
                )}
            </div>
        </div>
    );
}
