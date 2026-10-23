import type { Option } from '@/types/admin';
import type {
    Difficulty,
    QuestionSelectionMode,
    QuestionType,
    ResultsVisibility,
    TestStatus,
} from '@/types/enums';
import type { CategoryTreeNode, GradeResult } from '@/types/questions';
import type { RichTextDoc } from '@/types/rich-text';

/** Mirrors App\Http\Resources\TestResource. */
export type TestRow = {
    ulid: string;
    title: string;
    status: TestStatus;
    locked: boolean;
    questions_count: number;
    groups_count: number;
    attempts_count: number;
    opens_at: string | null;
    closes_at: string | null;
    category: { ulid: string; name: { ka: string; en: string } } | null;
    updated_at: string | null;
};

/** TestBuilderResource::questionRow(). */
export type TestQuestionRow = {
    ulid: string;
    excerpt: string;
    type: QuestionType;
    difficulty: Difficulty;
    points: string;
    points_override: string | null;
    archived: boolean;
};

/** Mirrors App\Http\Resources\TestBuilderResource. */
export type TestBuilderData = {
    ulid: string;
    title: string;
    description: string | null;
    category: string | null;
    status: TestStatus;
    locked: boolean;
    published_at: string | null;
    time_limit_minutes: number | null;
    max_attempts: number | null;
    pass_percent: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    selection_mode: QuestionSelectionMode;
    random_question_count: number | null;
    opens_at: string | null;
    closes_at: string | null;
    /** Display-timezone values for <input type="datetime-local">. */
    opens_at_local: string | null;
    closes_at_local: string | null;
    results_visibility: ResultsVisibility;
    show_correct_answers: boolean;
    issues_certificate: boolean;
    questions: TestQuestionRow[];
};

/** App\Domain\Tests\PublishChecklist::check(). */
export type ChecklistItem = {
    key: string;
    ok: boolean;
    severity: 'error' | 'warning';
    message: string;
};

/** TestController::options(). */
export type TestOptions = {
    categories: CategoryTreeNode[];
    statuses: Option<TestStatus>[];
    visibilities: (Option<ResultsVisibility> & { rank: number })[];
    selectionModes: Option<QuestionSelectionMode>[];
    timezone: string;
};

/** A question of a preview attempt (the student view of its snapshot). */
export type PreviewQuestion = {
    position: number;
    type: QuestionType;
    points: string;
    body: RichTextDoc;
    image_url: string | null;
    player: unknown;
};

/** SubmitPreviewAttempt result. */
export type PreviewResult = {
    score: string;
    max_score: string;
    percent: string;
    passed: boolean;
    questions: {
        position: number;
        type: QuestionType;
        body: RichTextDoc;
        points: string;
        result: GradeResult;
        review: unknown;
        explanation: RichTextDoc | null;
    }[];
};
