import { validateRows } from '../shared/validate';
import type { EditorErrors, Item, Translate } from '../shared/types';
import { MAX_ITEMS, MIN_ITEMS } from '../shared/types';
import type { MultipleChoiceScoring } from '@/types';

/** Editor input: items in the correct order (OrderingHandler::definitionRules()). */
export type OrderingInput = {
    items: { id?: string; text: string }[];
    scoring: MultipleChoiceScoring;
};

/** OrderingHandler::toPlayerPayload(): always shuffled. */
export type OrderingPayload = { items: Item[] };

export type OrderingAnswer = { order: string[] };

export type OrderingReview = {
    items: Item[];
    order: string[];
    answered: (Item & { is_correct?: boolean })[];
    correct_order?: string[];
    scoring?: MultipleChoiceScoring;
};

export const emptyInput = (): OrderingInput => ({
    items: [{ text: '' }, { text: '' }, { text: '' }],
    scoring: 'all_or_nothing',
});

/** Untouched, the answer is the order shown. */
export const initialAnswer = (payload: OrderingPayload): OrderingAnswer => ({
    order: payload.items.map((item) => item.id),
});

export function validate(input: OrderingInput, t: Translate): EditorErrors {
    return validateRows(input.items, 'items', t, {
        min: MIN_ITEMS,
        max: MAX_ITEMS,
    });
}
