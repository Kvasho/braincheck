import { ChoicePlayer } from '../shared/choice';
import type { PlayerProps } from '../shared/types';
import type { SingleChoiceAnswer, SingleChoicePayload } from './types';

export default function SingleChoicePlayer({
    payload,
    value,
    onChange,
    disabled,
    idPrefix,
}: PlayerProps<SingleChoicePayload, SingleChoiceAnswer>) {
    return (
        <ChoicePlayer
            mode="single"
            options={payload.options}
            selected={value.selected === null ? [] : [value.selected]}
            onChange={(selected) => onChange({ selected: selected[0] ?? null })}
            disabled={disabled}
            idPrefix={idPrefix}
        />
    );
}
