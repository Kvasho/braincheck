import { useAutoAnimate } from '@formkit/auto-animate/react';
import { ArrowRight, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslation } from '@/hooks/use-translation';
import { nextKey, SortableRows } from '../shared/sortable-rows';
import type { EditorProps } from '../shared/types';
import { ITEM_TEXT_MAX, MAX_ITEMS, MIN_ITEMS } from '../shared/types';
import type { MatchingInput } from './types';
import { MAX_DISTRACTORS } from './types';

export default function MatchingEditor({
    value,
    onChange,
    errors,
}: EditorProps<MatchingInput>) {
    const { t } = useTranslation();
    const [animated] = useAutoAnimate<HTMLUListElement>();
    const [keys, setKeys] = useState(() => value.pairs.map(() => nextKey()));

    if (keys.length !== value.pairs.length) {
        setKeys(value.pairs.map(() => nextKey()));
    }

    const setPairs = (pairs: MatchingInput['pairs']) =>
        onChange({ ...value, pairs });

    return (
        <div className="space-y-6">
            <fieldset className="space-y-2">
                <legend className="text-sm font-medium">{t('Pairs')}</legend>
                <ul ref={animated} className="space-y-2">
                    {value.pairs.map((pair, index) => (
                        <li
                            key={keys[index] ?? index}
                            className="flex flex-col gap-2 rounded-lg border bg-card p-2 sm:flex-row sm:items-start"
                        >
                            {(['left', 'right'] as const).map((side) => (
                                <div key={side} className="min-w-0 flex-1">
                                    <Input
                                        value={pair[side].text}
                                        maxLength={ITEM_TEXT_MAX}
                                        aria-label={
                                            side === 'left'
                                                ? t('Pair :number, left', {
                                                      number: index + 1,
                                                  })
                                                : t('Pair :number, right', {
                                                      number: index + 1,
                                                  })
                                        }
                                        onChange={(event) =>
                                            setPairs(
                                                value.pairs.map((current, i) =>
                                                    i === index
                                                        ? {
                                                              ...current,
                                                              [side]: {
                                                                  ...current[
                                                                      side
                                                                  ],
                                                                  text: event
                                                                      .target
                                                                      .value,
                                                              },
                                                          }
                                                        : current,
                                                ),
                                            )
                                        }
                                    />
                                    <InputError
                                        className="mt-1"
                                        message={
                                            errors[
                                                `pairs.${index}.${side}.text`
                                            ]
                                        }
                                    />
                                </div>
                            ))}
                            <ArrowRight
                                className="hidden size-4 shrink-0 self-center text-muted-foreground sm:order-1 sm:block"
                                aria-hidden="true"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="self-end sm:order-2 sm:self-start"
                                disabled={value.pairs.length <= MIN_ITEMS}
                                aria-label={t('Remove pair :number', {
                                    number: index + 1,
                                })}
                                onClick={() => {
                                    setKeys(keys.filter((_, i) => i !== index));
                                    setPairs(
                                        value.pairs.filter(
                                            (_, i) => i !== index,
                                        ),
                                    );
                                }}
                            >
                                <Trash2 aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={value.pairs.length >= MAX_ITEMS}
                    onClick={() => {
                        setKeys([...keys, nextKey()]);
                        setPairs([
                            ...value.pairs,
                            { left: { text: '' }, right: { text: '' } },
                        ]);
                    }}
                >
                    <Plus aria-hidden="true" />
                    {t('Add pair')}
                </Button>
                <InputError message={errors.pairs} />
            </fieldset>

            <fieldset className="space-y-2">
                <legend className="text-sm font-medium">
                    {t(
                        'Distractors (extra wrong answers on the right, optional)',
                    )}
                </legend>
                <SortableRows
                    rows={value.distractors}
                    onChange={(distractors) =>
                        onChange({ ...value, distractors })
                    }
                    min={0}
                    max={MAX_DISTRACTORS}
                    sortable={false}
                    newRow={() => ({ text: '' })}
                    label={t('Distractors')}
                    addLabel={t('Add distractor')}
                    rowLabel={(index) =>
                        t('Distractor :number', { number: index + 1 })
                    }
                    errorFor={(index) => errors[`distractors.${index}.text`]}
                />
                <InputError message={errors.distractors} />
            </fieldset>
        </div>
    );
}
