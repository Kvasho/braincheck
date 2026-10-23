import type { Option } from '@/types/admin';
import type { Difficulty, QuestionType } from '@/types/enums';
import type { RichTextDoc } from '@/types/rich-text';

/** Mirrors App\Http\Resources\QuestionResource. */
export type QuestionRow = {
    ulid: string;
    excerpt: string;
    type: QuestionType;
    difficulty: Difficulty;
    points: string;
    version: number;
    category: { ulid: string; name: { ka: string; en: string } } | null;
    tags: { ulid: string; name: string }[];
    tests_count: number;
    has_image: boolean;
    archived: boolean;
    owner: { ulid: string; name: string } | null;
    created_at: string | null;
    updated_at: string | null;
};

/** Mirrors App\Http\Resources\QuestionEditorResource (owner only). */
export type QuestionEditorData = {
    ulid: string;
    type: QuestionType;
    body: RichTextDoc;
    explanation: RichTextDoc | null;
    category: string | null;
    tags: string[];
    difficulty: Difficulty;
    points: string;
    definition: unknown;
    image_url: string | null;
    version: number;
    updated_at: string | null;
};

/** Mirrors App\Http\Resources\QuestionPreviewResource (what students see). */
export type QuestionPreview = {
    ulid: string;
    type: QuestionType;
    body: RichTextDoc;
    image_url: string | null;
    points: string;
    player: unknown;
};

export type GradeResult = {
    is_correct: boolean | null;
    awarded_points: string;
    needs_manual_grading: boolean;
};

export type CategoryTreeNode = {
    ulid: string;
    name: { ka: string; en: string };
    slug: string;
    children?: CategoryTreeNode[];
};

/** App\Support\QuestionListing::options(). */
export type QuestionOptions = {
    categories: CategoryTreeNode[];
    tags: Option[];
    types: Option<QuestionType>[];
    difficulties: Option<Difficulty>[];
    scorings: Option[];
};
