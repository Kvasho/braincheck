import { validateRows } from '../shared/validate';
import type { EditorErrors, Item, Translate } from '../shared/types';
import { MAX_ITEMS, MIN_ITEMS } from '../shared/types';

export const MAX_DISTRACTORS = 5;

type Row = { id?: string; text: string };

/** Editor input: MatchingHandler::definitionRules(). */
export type MatchingInput = {
    pairs: { left: Row; right: Row }[];
    distractors: Row[];
};

/** MatchingHandler::toPlayerPayload(): the right side is always shuffled. */
export type MatchingPayload = { left: Item[]; right: Item[] };

export type MatchingPair = { left: string; right: string };

export type MatchingAnswer = { pairs: MatchingPair[] };

export type MatchingReview = {
    left: Item[];
    right: Item[];
    given_pairs: (MatchingPair & { is_correct?: boolean })[];
    correct_pairs?: MatchingPair[];
};

export const emptyInput = (): MatchingInput => ({
    pairs: [
        { left: { text: '' }, right: { text: '' } },
        { left: { text: '' }, right: { text: '' } },
    ],
    distractors: [],
});

export const initialAnswer = (): MatchingAnswer => ({ pairs: [] });

export function validate(input: MatchingInput, t: Translate): EditorErrors {
    const errors: EditorErrors = {
        ...validateRows(input.distractors, 'distractors', t, {
            min: 0,
            max: MAX_DISTRACTORS,
        }),
    };

    if (input.pairs.length < MIN_ITEMS || input.pairs.length > MAX_ITEMS) {
        errors.pairs = t('Use between :min and :max pairs.', {
            min: MIN_ITEMS,
            max: MAX_ITEMS,
        });
    }

    input.pairs.forEach((pair, index) => {
        (['left', 'right'] as const).forEach((side) => {
            if (pair[side].text.trim() === '') {
                errors[`pairs.${index}.${side}.text`] = t('Enter a text.');
            }
        });
    });

    return errors;
}
