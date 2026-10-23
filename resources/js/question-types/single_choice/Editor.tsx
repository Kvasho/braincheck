import { ChoiceEditor } from '../shared/choice';
import type { EditorProps } from '../shared/types';
import type { SingleChoiceInput } from './types';

export default function SingleChoiceEditor({
    value,
    onChange,
    errors,
}: EditorProps<SingleChoiceInput>) {
    return (
        <ChoiceEditor
            mode="single"
            options={value.options}
            onChange={(options) => onChange({ ...value, options })}
            errors={errors}
        />
    );
}
