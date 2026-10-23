import { validateRows } from '../shared/validate';
import type { EditorErrors, Item, Translate } from '../shared/types';
import { MAX_ITEMS, MIN_ITEMS } from '../shared/types';

/** Editor input: SingleChoiceHandler::definitionRules(). */
export type SingleChoiceInput = {
    options: { id?: string; text: string; correct: boolean }[];
};

/** SingleChoiceHandler::toPlayerPayload(). */
export type SingleChoicePayload = { options: Item[] };

export type SingleChoiceAnswer = { selected: string | null };

/** SingleChoiceHandler::toReviewPayload(). */
export type SingleChoiceReview = {
    options: (Item & { selected: boolean; is_correct?: boolean })[];
    selected: string | null;
    correct?: string;
};

export const emptyInput = (): SingleChoiceInput => ({
    options: [
        { text: '', correct: true },
        { text: '', correct: false },
    ],
});

export const initialAnswer = (): SingleChoiceAnswer => ({ selected: null });

export function validate(input: SingleChoiceInput, t: Translate): EditorErrors {
    const errors = validateRows(input.options, 'options', t, {
        min: MIN_ITEMS,
        max: MAX_ITEMS,
    });

    if (input.options.filter((option) => option.correct).length !== 1) {
        errors.options ??= t('Mark exactly one option as correct.');
    }

    return errors;
}
