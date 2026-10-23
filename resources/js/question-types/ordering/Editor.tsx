import InputError from '@/components/input-error';
import { useTranslation } from '@/hooks/use-translation';
import { ScoringSelect } from '../shared/scoring-select';
import { SortableRows } from '../shared/sortable-rows';
import type { EditorProps } from '../shared/types';
import { MAX_ITEMS, MIN_ITEMS } from '../shared/types';
import type { OrderingInput } from './types';

export default function OrderingEditor({
    value,
    onChange,
    errors,
    scorings,
}: EditorProps<OrderingInput>) {
    const { t } = useTranslation();

    return (
        <div className="space-y-6">
            <fieldset className="space-y-2">
                <legend className="text-sm font-medium">
                    {t('Items, in the correct order')}
                </legend>
                <p className="text-xs text-muted-foreground">
                    {t('Students always see them shuffled.')}
                </p>
                <SortableRows
                    rows={value.items}
                    onChange={(items) => onChange({ ...value, items })}
                    min={MIN_ITEMS}
                    max={MAX_ITEMS}
                    newRow={() => ({ text: '' })}
                    label={t('Items')}
                    addLabel={t('Add item')}
                    rowLabel={(index) =>
                        t('Position :number', { number: index + 1 })
                    }
                    errorFor={(index) => errors[`items.${index}.text`]}
                    renderControl={(_, index) => (
                        <span className="tabular mt-2 w-6 text-right text-sm text-muted-foreground">
                            {index + 1}.
                        </span>
                    )}
                />
                <InputError message={errors.items} />
            </fieldset>
            <ScoringSelect
                value={value.scoring}
                options={scorings}
                onChange={(scoring) => onChange({ ...value, scoring })}
                error={errors.scoring}
            />
        </div>
    );
}
