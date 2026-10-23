import { useId } from 'react';
import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { ReviewMark } from './review-mark';
import { SortableRows } from './sortable-rows';
import type { EditorErrors, Item } from './types';
import { MAX_ITEMS, MIN_ITEMS } from './types';

type ChoiceRow = { id?: string; text: string; correct: boolean };

/**
 * Options editor for single / multiple choice: a radio (single) or checkbox
 * (multiple) marks the correct option(s).
 */
export function ChoiceEditor({
    mode,
    options,
    onChange,
    errors,
}: {
    mode: 'single' | 'multiple';
    options: ChoiceRow[];
    onChange: (options: ChoiceRow[]) => void;
    errors: EditorErrors;
}) {
    const { t } = useTranslation();
    const group = useId();

    const toggle = (index: number) =>
        onChange(
            options.map((option, i) =>
                mode === 'single'
                    ? { ...option, correct: i === index }
                    : i === index
                      ? { ...option, correct: !option.correct }
                      : option,
            ),
        );

    return (
        <fieldset className="space-y-2">
            <legend className="text-sm font-medium">
                {mode === 'single'
                    ? t('Options (mark the correct one)')
                    : t('Options (mark every correct one)')}
            </legend>
            <SortableRows
                rows={options}
                onChange={onChange}
                min={MIN_ITEMS}
                max={MAX_ITEMS}
                newRow={() => ({ text: '', correct: false })}
                label={t('Options')}
                addLabel={t('Add option')}
                rowLabel={(index) => t('Option :number', { number: index + 1 })}
                errorFor={(index) => errors[`options.${index}.text`]}
                renderControl={(option, index) => (
                    <label className="mt-2 flex items-center gap-1.5 text-xs text-muted-foreground">
                        <input
                            type={mode === 'single' ? 'radio' : 'checkbox'}
                            name={`${group}-correct`}
                            checked={option.correct}
                            onChange={() => toggle(index)}
                            className="size-4 accent-success"
                            aria-label={t('Option :number is correct', {
                                number: index + 1,
                            })}
                        />
                        <span aria-hidden="true">{t('Correct')}</span>
                    </label>
                )}
            />
            <InputError message={errors.options} />
        </fieldset>
    );
}

/**
 * Player for single / multiple choice. Knows nothing about correctness.
 */
export function ChoicePlayer({
    mode,
    options,
    selected,
    onChange,
    disabled,
    idPrefix,
}: {
    mode: 'single' | 'multiple';
    options: Item[];
    selected: string[];
    onChange: (selected: string[]) => void;
    disabled?: boolean;
    idPrefix: string;
}) {
    const { t } = useTranslation();

    const toggle = (id: string) =>
        onChange(
            mode === 'single'
                ? [id]
                : selected.includes(id)
                  ? selected.filter((value) => value !== id)
                  : [...selected, id],
        );

    return (
        <fieldset className="space-y-2" disabled={disabled}>
            <legend className="sr-only">
                {mode === 'single'
                    ? t('Choose one answer')
                    : t('Choose all answers that apply')}
            </legend>
            {options.map((option) => {
                const checked = selected.includes(option.id);
                const inputId = `${idPrefix}-${option.id}`;

                return (
                    <label
                        key={option.id}
                        htmlFor={inputId}
                        className={cn(
                            'flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-colors hover:bg-muted/40 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                            checked && 'border-primary bg-primary/5',
                        )}
                    >
                        <input
                            id={inputId}
                            type={mode === 'single' ? 'radio' : 'checkbox'}
                            name={`${idPrefix}-choice`}
                            checked={checked}
                            onChange={() => toggle(option.id)}
                            className="mt-1 size-4 accent-primary"
                        />
                        <span className="min-w-0 break-words">
                            {option.text}
                        </span>
                    </label>
                );
            })}
        </fieldset>
    );
}

/**
 * Review for single / multiple choice: the student's choice and, when
 * revealed, which options are correct.
 */
export function ChoiceReview({
    options,
}: {
    options: (Item & { selected: boolean; is_correct?: boolean })[];
}) {
    return (
        <ul className="space-y-2">
            {options.map((option) => (
                <li key={option.id}>
                    <ReviewMark
                        correct={option.is_correct}
                        selected={option.selected}
                    >
                        {option.text}
                    </ReviewMark>
                </li>
            ))}
        </ul>
    );
}
