import type { LucideIcon } from 'lucide-react';
import type { ComponentType } from 'react';
import type { Replacements, TranslationKey } from '@/hooks/use-translation';
import type { Option, QuestionType } from '@/types';

/** An option / item as students see it. */
export type Item = { id: string; text: string };

/** Validation messages keyed like the backend (relative to `definition`, e.g. "options.0.text"). */
export type EditorErrors = Record<string, string>;

export type Translate = (
    key: TranslationKey,
    replacements?: Replacements,
) => string;

export type EditorProps<TInput> = {
    value: TInput;
    onChange: (value: TInput) => void;
    errors: EditorErrors;
    scorings: Option[];
};

/**
 * Players are controlled and know nothing about correctness: they only get
 * the student payload (App\Domain\Questions\Types\*Handler::toPlayerPayload).
 */
export type PlayerProps<TPayload, TAnswer> = {
    payload: TPayload;
    value: TAnswer;
    onChange: (value: TAnswer) => void;
    disabled?: boolean;
    /** Unique prefix for element ids (several players can share a page). */
    idPrefix: string;
};

export type ReviewProps<TReview> = { review: TReview };

/**
 * One question type on the frontend (a "Synapse module"): editor, player and
 * review, plus the client-side mirror of the backend definition rules.
 */
export type QuestionTypeModule<TInput, TPayload, TAnswer, TReview> = {
    type: QuestionType;
    icon: LucideIcon;
    label: TranslationKey;
    description: TranslationKey;
    emptyInput: () => TInput;
    /** The answer before the student does anything (ordering: the shown order). */
    initialAnswer: (payload: TPayload) => TAnswer;
    /** Mirrors the handler's definitionRules() for instant feedback (the server decides). */
    validate: (input: TInput, t: Translate) => EditorErrors;
    Editor: ComponentType<EditorProps<TInput>>;
    Player: ComponentType<PlayerProps<TPayload, TAnswer>>;
    Review: ComponentType<ReviewProps<TReview>>;
};

export const MIN_ITEMS = 2;
export const MAX_ITEMS = 10;
export const ITEM_TEXT_MAX = 500;
