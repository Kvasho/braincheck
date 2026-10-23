import type { TestQuestionRow } from '@/types';

/** Same as QuestionRequest::POINTS_PATTERN. */
export const POINTS_PATTERN = /^\d{1,4}(\.\d{1,2})?$/;

/** SyncTestQuestions::MAX_QUESTIONS. */
export const MAX_TEST_QUESTIONS = 200;

/** "2.5" → 250. Null for anything that is not a valid points value. */
export function toCents(points: string): number | null {
    if (!POINTS_PATTERN.test(points)) {
        return null;
    }

    const [whole = '0', fraction = ''] = points.split('.');

    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}

/** 250 → "2.50" (integer maths, no floating point rounding). */
export function formatCents(cents: number): string {
    return `${Math.floor(cents / 100)}.${String(cents % 100).padStart(2, '0')}`;
}

/** Points a question is worth in this test (override, else its own points). */
export function effectivePoints(question: TestQuestionRow): string {
    return question.points_override !== null && question.points_override !== ''
        ? question.points_override
        : question.points;
}

/** Total points of the questions, in cents; null when an override is invalid. */
export function totalCents(questions: TestQuestionRow[]): number | null {
    let total = 0;

    for (const question of questions) {
        const cents = toCents(effectivePoints(question));

        if (cents === null) {
            return null;
        }

        total += cents;
    }

    return total;
}

/** The UTC offset of `timeZone` at `date`, as "+04:00". */
function offsetOf(timeZone: string, date: Date): string {
    const name =
        new Intl.DateTimeFormat('en-US', {
            timeZone,
            timeZoneName: 'longOffset',
        })
            .formatToParts(date)
            .find((part) => part.type === 'timeZoneName')?.value ?? 'GMT';

    const offset = name.replace('GMT', '');

    return offset === '' ? '+00:00' : offset;
}

/**
 * A datetime-local value ("2026-11-01T02:00") meant in `timeZone` as a Date.
 * Mirrors App\Support\AppTime::fromLocalInput (the server is authoritative).
 */
export function zonedInputToDate(value: string, timeZone: string): Date | null {
    if (!/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/.test(value)) {
        return null;
    }

    const guess = new Date(`${value}:00Z`);

    if (Number.isNaN(guess.getTime())) {
        return null;
    }

    return new Date(`${value}:00${offsetOf(timeZone, guess)}`);
}
