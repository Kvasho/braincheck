import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/use-translation';
import type { EditorProps } from '../shared/types';
import type { NumericInput } from './types';

export default function NumericEditor({
    value,
    onChange,
    errors,
}: EditorProps<NumericInput>) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-4 sm:grid-cols-3">
            <div className="grid gap-2">
                <Label htmlFor="numeric-correct">{t('Correct answer')}</Label>
                <Input
                    id="numeric-correct"
                    inputMode="decimal"
                    className="tabular"
                    value={value.correct}
                    onChange={(event) =>
                        onChange({ ...value, correct: event.target.value })
                    }
                />
                <InputError message={errors.correct} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="numeric-tolerance">{t('Tolerance (±)')}</Label>
                <Input
                    id="numeric-tolerance"
                    inputMode="decimal"
                    className="tabular"
                    value={value.tolerance}
                    onChange={(event) =>
                        onChange({ ...value, tolerance: event.target.value })
                    }
                />
                <InputError message={errors.tolerance} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="numeric-unit">{t('Unit (optional)')}</Label>
                <Input
                    id="numeric-unit"
                    maxLength={32}
                    value={value.unit ?? ''}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            unit: event.target.value || null,
                        })
                    }
                />
                <InputError message={errors.unit} />
            </div>
            <p className="text-xs text-muted-foreground sm:col-span-3">
                {t(
                    'An answer is correct when it differs from the correct answer by at most the tolerance.',
                )}
            </p>
        </div>
    );
}
