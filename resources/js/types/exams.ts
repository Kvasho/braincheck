import type { QuestionType } from '@/types/enums';
import type { RichTextDoc } from '@/types/rich-text';

export type ExamSection = 'available' | 'in_progress' | 'upcoming' | 'closed';

/** Mirrors App\Domain\Attempts\StudentExams::card(). */
export type ExamCard = {
    ulid: string;
    title: string;
    description: string | null;
    teacher: string;
    groups: string[];
    time_limit_minutes: number | null;
    question_count: number;
    pass_percent: number;
    attempts_used: number;
    max_attempts: number | null;
    opens_at: string | null;
    closes_at: string | null;
    section: ExamSection;
    attempt: { ulid: string; deadline_at: string | null } | null;
    best_percent: string | null;
};

/** Mirrors App\Http\Resources\PlayerAttemptResource. */
export type PlayerAttempt = {
    ulid: string;
    test: { title: string };
    deadline_at: string | null;
    server_now: string;
    question_count: number;
    questions: PlayerQuestion[];
    saved: {
        position: number;
        value: unknown;
        flagged: boolean;
        answered: boolean;
    }[];
};

export type PlayerQuestion = {
    position: number;
    type: QuestionType;
    points: string;
    body: RichTextDoc;
    image_url: string | null;
    player: unknown;
};

/** AttemptResultController::show() (until the Stage 9 result page). */
export type SubmittedAttempt = {
    ulid: string;
    test: { title: string };
    status: 'submitted' | 'timed_out';
    submitted_at: string | null;
};
