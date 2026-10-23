import type { EditorErrors, Translate } from '../shared/types';

/** Decimal strings (never floats), as NumericHandler::definitionRules() expects. */
export type NumericInput = {
    correct: string;
    tolerance: string;
    unit: string | null;
};

export type NumericPayload = { unit: string | null };

export type NumericAnswer = { value: string | null };

export type NumericReview = {
    value: string | null;
    unit: string | null;
    correct?: string;
    tolerance?: string;
};

/** Mirrors NumericHandler::DECIMAL_PATTERN (a decimal comma is accepted). */
export const DECIMAL_PATTERN = /^-?\d{1,20}([.,]\d{1,10})?$/;

export const emptyInput = (): NumericInput => ({
    correct: '',
    tolerance: '0',
    unit: null,
});

export const initialAnswer = (): NumericAnswer => ({ value: null });

export function validate(input: NumericInput, t: Translate): EditorErrors {
    const errors: EditorErrors = {};

    if (!DECIMAL_PATTERN.test(input.correct.trim())) {
        errors.correct = t('Enter a decimal number such as 9.81.');
    }

    if (!/^\d{1,20}([.,]\d{1,10})?$/.test(input.tolerance.trim())) {
        errors.tolerance = t('Enter zero or a positive decimal number.');
    }

    if ((input.unit ?? '').length > 32) {
        errors.unit = t('Use at most :max characters.', { max: 32 });
    }

    return errors;
}
