import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import type { PlayerProps } from '../shared/types';
import type { TextAnswer, TextPayload } from './types';

export default function TextPlayer({
    payload,
    value,
    onChange,
    disabled,
    idPrefix,
}: PlayerProps<TextPayload, TextAnswer>) {
    const { t } = useTranslation();
    const length = value.text?.length ?? 0;

    return (
        <div className="grid gap-2">
            <Label htmlFor={`${idPrefix}-text`}>{t('Your answer')}</Label>
            <Textarea
                id={`${idPrefix}-text`}
                rows={6}
                maxLength={payload.max_length}
                disabled={disabled}
                value={value.text ?? ''}
                onChange={(event) =>
                    onChange({ text: event.target.value || null })
                }
            />
            <p className="tabular text-right text-xs text-muted-foreground">
                {length} / {payload.max_length}
            </p>
        </div>
    );
}
