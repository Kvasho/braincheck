import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/use-translation';
import type { EditorProps } from '../shared/types';
import type { TextInput } from './types';
import { TEXT_MAX_LENGTH } from './types';

export default function TextEditor({
    value,
    onChange,
    errors,
}: EditorProps<TextInput>) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-4">
            <div className="grid max-w-xs gap-2">
                <Label htmlFor="text-max-length">
                    {t('Maximum answer length (characters)')}
                </Label>
                <Input
                    id="text-max-length"
                    type="number"
                    min={1}
                    max={TEXT_MAX_LENGTH}
                    className="tabular"
                    value={value.max_length}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            max_length:
                                Number.parseInt(event.target.value, 10) || 0,
                        })
                    }
                />
                <InputError message={errors.max_length} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="text-reference">
                    {t('Model answer (only you can see it)')}
                </Label>
                <Textarea
                    id="text-reference"
                    rows={4}
                    maxLength={TEXT_MAX_LENGTH}
                    value={value.reference_answer ?? ''}
                    onChange={(event) =>
                        onChange({
                            ...value,
                            reference_answer: event.target.value || null,
                        })
                    }
                />
                <InputError message={errors.reference_answer} />
            </div>
            <p className="text-xs text-muted-foreground">
                {t('Text answers are graded by you after the test.')}
            </p>
        </div>
    );
}
