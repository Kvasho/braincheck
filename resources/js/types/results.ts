import type { QuestionType } from '@/types/enums';
import type { RichTextDoc } from '@/types/rich-text';

/** Mirrors App\Http\Resources\StudentAttemptResultResource. */
export type StudentAttemptResult = {
    ulid: string;
    test: { ulid: string; title: string };
    status: 'submitted' | 'timed_out';
    started_at: string;
    submitted_at: string | null;
    duration_seconds: number | null;
    visibility: {
        score: boolean;
        correct_answers: boolean;
        awaiting_review: boolean;
        later: boolean;
    };
    result: {
        score: string | null;
        max_score: string | null;
        percent: string | null;
        passed: boolean | null;
    } | null;
    questions: {
        position: number;
        type: QuestionType;
        body: RichTextDoc;
        points: string;
        awarded_points: string | null;
        review: unknown;
        feedback: string | null;
        explanation: RichTextDoc | null;
    }[];
};

/** Mirrors App\Http\Resources\StudentResultRowResource. */
export type StudentResultRow = {
    ulid: string;
    test: string;
    status: string;
    submitted_at: string | null;
    visible: boolean;
    awaiting_review: boolean;
    percent: string | null;
    passed: boolean | null;
};

export type ProgressPoint = {
    date: string | null;
    percent: string;
    test: string;
};

export type Strength = {
    category: { ka: string; en: string } | null;
    average: string;
    count: number;
};

/** App\Domain\Analytics\TestAnalytics::overview(). */
export type AnalyticsOverview = {
    attempt_count: number;
    students: number;
    average: string | null;
    median: string | null;
    standard_deviation: string | null;
    pass_rate: string | null;
    average_duration_seconds: number | null;
    distribution: { from: number; to: number; count: number }[];
};

export type Distractor = {
    id: string;
    text: string;
    correct: boolean;
    picks: number;
    upper: number;
    lower: number;
    flags: ('never_picked' | 'strong_pick')[];
};

/** App\Domain\Analytics\TestAnalytics::questions(). */
export type QuestionStat = {
    question: string;
    type: QuestionType;
    excerpt: string;
    responses: number;
    difficulty: string | null;
    discrimination: string | null;
    average_points: string | null;
    manual_grades: number;
    needs_review: boolean;
    distractors: Distractor[] | null;
    computed_at: string;
};

/** Mirrors App\Http\Resources\AnalyticsAttemptRowResource. */
export type AnalyticsAttemptRow = {
    ulid: string;
    student: { name: string; email: string };
    status: string;
    grading_status: string;
    percent: string | null;
    passed: boolean | null;
    submitted_at: string | null;
    duration_seconds: number | null;
    events: number;
    severity: 'none' | 'few' | 'many';
};

export type GroupComparison = {
    ulid: string;
    name: string;
    responses: number;
    average: string | null;
};
