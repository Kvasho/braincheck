import type { EditorRow } from './sortable-rows';
import type { EditorErrors, Translate } from './types';
import { ITEM_TEXT_MAX } from './types';

/**
 * Client mirror of BaseQuestionTypeHandler::itemRowRules(): row count and
 * non-blank text up to ITEM_TEXT_MAX characters.
 */
export function validateRows(
    rows: EditorRow[],
    field: string,
    t: Translate,
    { min, max }: { min: number; max: number },
): EditorErrors {
    const errors: EditorErrors = {};

    if (rows.length < min || rows.length > max) {
        errors[field] = t('Use between :min and :max entries.', { min, max });
    }

    rows.forEach((row, index) => {
        if (row.text.trim() === '') {
            errors[`${field}.${index}.text`] = t('Enter a text.');
        } else if (row.text.length > ITEM_TEXT_MAX) {
            errors[`${field}.${index}.text`] = t(
                'Use at most :max characters.',
                { max: ITEM_TEXT_MAX },
            );
        }
    });

    return errors;
}

/** First error for a field or its children (e.g. "options" or "options.0.correct"). */
export function errorFor(
    errors: EditorErrors,
    field: string,
): string | undefined {
    return errors[field];
}
