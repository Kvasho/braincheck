import { ChoiceEditor } from '../shared/choice';
import { ScoringSelect } from '../shared/scoring-select';
import type { EditorProps } from '../shared/types';
import type { MultipleChoiceInput } from './types';

export default function MultipleChoiceEditor({
    value,
    onChange,
    errors,
    scorings,
}: EditorProps<MultipleChoiceInput>) {
    return (
        <div className="space-y-6">
            <ChoiceEditor
                mode="multiple"
                options={value.options}
                onChange={(options) => onChange({ ...value, options })}
                errors={errors}
            />
            <ScoringSelect
                value={value.scoring}
                options={scorings}
                onChange={(scoring) => onChange({ ...value, scoring })}
                error={errors.scoring}
            />
        </div>
    );
}
