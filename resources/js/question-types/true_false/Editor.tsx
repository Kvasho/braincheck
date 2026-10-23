import { useId } from 'react';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import type { EditorProps } from '../shared/types';
import type { TrueFalseInput } from './types';

export default function TrueFalseEditor({
    value,
    onChange,
    errors,
}: EditorProps<TrueFalseInput>) {
    const { t } = useTranslation();
    const name = useId();

    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">
                {t('The statement in the question is')}
            </legend>
            <div className="flex gap-4">
                {[true, false].map((option) => (
                    <label
                        key={String(option)}
                        className="flex items-center gap-2 rounded-lg border px-4 py-2"
                    >
                        <input
                            type="radio"
                            name={name}
                            checked={value.correct === option}
                            onChange={() => onChange({ correct: option })}
                            className="size-4 accent-success"
                        />
                        {option ? t('True') : t('False')}
                    </label>
                ))}
            </div>
            <InputError message={errors.correct} />
        </fieldset>
    );
}
