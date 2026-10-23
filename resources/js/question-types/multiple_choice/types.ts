import { validateRows } from '../shared/validate';
import type { EditorErrors, Item, Translate } from '../shared/types';
import { MAX_ITEMS, MIN_ITEMS } from '../shared/types';
import type { MultipleChoiceScoring } from '@/types';

/** Editor input: MultipleChoiceHandler::definitionRules(). */
export type MultipleChoiceInput = {
    options: { id?: string; text: string; correct: boolean }[];
    scoring: MultipleChoiceScoring;
};

/** MultipleChoiceHandler::toPlayerPayload() (no count of correct options). */
export type MultipleChoicePayload = { options: Item[] };

export type MultipleChoiceAnswer = { selected: string[] };

export type MultipleChoiceReview = {
    options: (Item & { selected: boolean; is_correct?: boolean })[];
    selected: string[];
    correct?: string[];
    scoring?: MultipleChoiceScoring;
};

export const emptyInput = (): MultipleChoiceInput => ({
    options: [
        { text: '', correct: true },
        { text: '', correct: false },
        { text: '', correct: false },
    ],
    scoring: 'partial',
});

export const initialAnswer = (): MultipleChoiceAnswer => ({ selected: [] });

export function validate(
    input: MultipleChoiceInput,
    t: Translate,
): EditorErrors {
    const errors = validateRows(input.options, 'options', t, {
        min: MIN_ITEMS,
        max: MAX_ITEMS,
    });

    if (!input.options.some((option) => option.correct)) {
        errors.options ??= t('Mark at least one option as correct.');
    }

    return errors;
}
