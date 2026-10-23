import { ChoicePlayer } from '../shared/choice';
import type { PlayerProps } from '../shared/types';
import type { MultipleChoiceAnswer, MultipleChoicePayload } from './types';

export default function MultipleChoicePlayer({
    payload,
    value,
    onChange,
    disabled,
    idPrefix,
}: PlayerProps<MultipleChoicePayload, MultipleChoiceAnswer>) {
    return (
        <ChoicePlayer
            mode="multiple"
            options={payload.options}
            selected={value.selected}
            onChange={(selected) => onChange({ selected })}
            disabled={disabled}
            idPrefix={idPrefix}
        />
    );
}
