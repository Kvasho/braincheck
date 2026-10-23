import type { EditorErrors, Translate } from '../shared/types';

export const TEXT_MAX_LENGTH = 5000;

export type TextInput = { max_length: number; reference_answer: string | null };

export type TextPayload = { max_length: number };

export type TextAnswer = { text: string | null };

/** The reference answer is never part of the review (teacher only). */
export type TextReview = { text: string };

export const emptyInput = (): TextInput => ({
    max_length: 1000,
    reference_answer: null,
});

export const initialAnswer = (): TextAnswer => ({ text: null });

export function validate(input: TextInput, t: Translate): EditorErrors {
    const errors: EditorErrors = {};

    if (
        !Number.isInteger(input.max_length) ||
        input.max_length < 1 ||
        input.max_length > TEXT_MAX_LENGTH
    ) {
        errors.max_length = t('Use a number from 1 to :max.', {
            max: TEXT_MAX_LENGTH,
        });
    }

    return errors;
}
