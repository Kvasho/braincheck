import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import type { PlayerProps } from '../shared/types';
import type { TrueFalseAnswer, TrueFalsePayload } from './types';

export default function TrueFalsePlayer({
    value,
    onChange,
    disabled,
    idPrefix,
}: PlayerProps<TrueFalsePayload, TrueFalseAnswer>) {
    const { t } = useTranslation();

    return (
        <fieldset className="flex flex-wrap gap-3" disabled={disabled}>
            <legend className="sr-only">{t('True or false?')}</legend>
            {[true, false].map((option) => (
                <label
                    key={String(option)}
                    htmlFor={`${idPrefix}-${String(option)}`}
                    className={cn(
                        'flex min-w-32 cursor-pointer items-center gap-3 rounded-lg border px-4 py-3 hover:bg-muted/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                        value.value === option && 'border-primary bg-primary/5',
                    )}
                >
                    <input
                        id={`${idPrefix}-${String(option)}`}
                        type="radio"
                        name={`${idPrefix}-value`}
                        checked={value.value === option}
                        onChange={() => onChange({ value: option })}
                        className="size-4 accent-primary"
                    />
                    {option ? t('True') : t('False')}
                </label>
            ))}
        </fieldset>
    );
}
