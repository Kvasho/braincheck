import type { QuestionType } from '@/types/enums';
import type { RichTextDoc } from '@/types/rich-text';

/** Mirrors App\Http\Resources\GradingQueueItemResource. */
export type GradingQueueItem = {
    attempt: string;
    position: number;
    test: { ulid: string; title: string };
    excerpt: string;
    /** "Student #N" in anonymous mode, otherwise the name. */
    student: string;
    submitted_at: string | null;
};

/** Mirrors App\Http\Resources\GradingAnswerResource (test owner only). */
export type GradingAnswer = {
    attempt: string;
    position: number;
    type: QuestionType;
    points: string;
    body: RichTextDoc;
    /** Free-text answers are graded by the teacher; the others can be overridden. */
    manual: boolean;
    reference_answer: string | null;
    /** The student's text, a plain string (rendered as text, never HTML). */
    text: string | null;
    review: unknown;
    awarded_points: string | null;
    graded: boolean;
    feedback: string | null;
    version: string | null;
};

/** One question version used by a test's attempts (regrade page). */
export type RegradeVersion = {
    question: string;
    version: number;
    type: QuestionType;
    excerpt: string;
    /** Attempts that use this version. */
    uses: number;
    definition: unknown;
};

/** RegradeTestQuestion::preview(). */
export type RegradePreview = {
    changes: {
        attempt: string;
        student: string;
        status: string;
        old_score: string | null;
        new_score: string | null;
        old_passed: boolean | null;
        new_passed: boolean | null;
    }[];
    summary: { affected: number; changed: number; passed_flips: number };
};
