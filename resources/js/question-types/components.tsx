import { questionTypeModule } from './registry';
import type { EditorErrors } from './shared/types';
import type { Option, QuestionType } from '@/types';

/** Renders the Editor of `type` (the value must be that type's editor input). */
export function QuestionTypeEditor({
    type,
    value,
    onChange,
    errors,
    scorings,
}: {
    type: QuestionType;
    value: unknown;
    onChange: (value: unknown) => void;
    errors: EditorErrors;
    scorings: Option[];
}) {
    const { Editor } = questionTypeModule(type);

    return (
        <Editor
            value={value}
            onChange={onChange}
            errors={errors}
            scorings={scorings}
        />
    );
}

/** Renders the Player of `type` (student payload + controlled answer). */
export function QuestionTypePlayer({
    type,
    payload,
    value,
    onChange,
    disabled,
    idPrefix,
}: {
    type: QuestionType;
    payload: unknown;
    value: unknown;
    onChange: (value: unknown) => void;
    disabled?: boolean;
    idPrefix: string;
}) {
    const { Player } = questionTypeModule(type);

    return (
        <Player
            payload={payload}
            value={value}
            onChange={onChange}
            disabled={disabled}
            idPrefix={idPrefix}
        />
    );
}

/** Renders the Review of `type`. */
export function QuestionTypeReview({
    type,
    review,
}: {
    type: QuestionType;
    review: unknown;
}) {
    const { Review } = questionTypeModule(type);

    return <Review review={review} />;
}
