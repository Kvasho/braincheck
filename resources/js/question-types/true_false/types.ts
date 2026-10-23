import type { EditorErrors } from '../shared/types';

export type TrueFalseInput = { correct: boolean };

/** TrueFalseHandler::toPlayerPayload(): the statement is the question body. */
export type TrueFalsePayload = Record<string, never>;

export type TrueFalseAnswer = { value: boolean | null };

export type TrueFalseReview = { value: boolean | null; correct?: boolean };

export const emptyInput = (): TrueFalseInput => ({ correct: true });

export const initialAnswer = (): TrueFalseAnswer => ({ value: null });

export function validate(): EditorErrors {
    return {};
}
