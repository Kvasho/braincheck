import {
    ArrowDownUp,
    Calculator,
    CircleDot,
    Link2,
    ListChecks,
    PencilLine,
    ToggleRight,
} from 'lucide-react';
import type { QuestionType } from '@/types';
import MatchingEditor from './matching/Editor';
import MatchingPlayer from './matching/Player';
import MatchingReview from './matching/Review';
import * as matching from './matching/types';
import MultipleChoiceEditor from './multiple_choice/Editor';
import MultipleChoicePlayer from './multiple_choice/Player';
import MultipleChoiceReview from './multiple_choice/Review';
import * as multipleChoice from './multiple_choice/types';
import NumericEditor from './numeric/Editor';
import NumericPlayer from './numeric/Player';
import NumericReview from './numeric/Review';
import * as numeric from './numeric/types';
import OrderingEditor from './ordering/Editor';
import OrderingPlayer from './ordering/Player';
import OrderingReview from './ordering/Review';
import * as ordering from './ordering/types';
import type { QuestionTypeModule } from './shared/types';
import SingleChoiceEditor from './single_choice/Editor';
import SingleChoicePlayer from './single_choice/Player';
import SingleChoiceReview from './single_choice/Review';
import * as singleChoice from './single_choice/types';
import TextEditor from './text/Editor';
import TextPlayer from './text/Player';
import TextReview from './text/Review';
import * as text from './text/types';
import TrueFalseEditor from './true_false/Editor';
import TrueFalsePlayer from './true_false/Player';
import TrueFalseReview from './true_false/Review';
import * as trueFalse from './true_false/types';

/** Input / payload / answer / review types of every question type. */
type Modules = {
    single_choice: QuestionTypeModule<
        singleChoice.SingleChoiceInput,
        singleChoice.SingleChoicePayload,
        singleChoice.SingleChoiceAnswer,
        singleChoice.SingleChoiceReview
    >;
    multiple_choice: QuestionTypeModule<
        multipleChoice.MultipleChoiceInput,
        multipleChoice.MultipleChoicePayload,
        multipleChoice.MultipleChoiceAnswer,
        multipleChoice.MultipleChoiceReview
    >;
    true_false: QuestionTypeModule<
        trueFalse.TrueFalseInput,
        trueFalse.TrueFalsePayload,
        trueFalse.TrueFalseAnswer,
        trueFalse.TrueFalseReview
    >;
    numeric: QuestionTypeModule<
        numeric.NumericInput,
        numeric.NumericPayload,
        numeric.NumericAnswer,
        numeric.NumericReview
    >;
    text: QuestionTypeModule<
        text.TextInput,
        text.TextPayload,
        text.TextAnswer,
        text.TextReview
    >;
    ordering: QuestionTypeModule<
        ordering.OrderingInput,
        ordering.OrderingPayload,
        ordering.OrderingAnswer,
        ordering.OrderingReview
    >;
    matching: QuestionTypeModule<
        matching.MatchingInput,
        matching.MatchingPayload,
        matching.MatchingAnswer,
        matching.MatchingReview
    >;
};

/**
 * The Synapse registry: one module per question type. Typed as a record over
 * QuestionType, so a missing (or extra) type is a TypeScript error; the PHP
 * enum, the TS enum and these keys are also compared by tests/Arch/EnumParityTest.php.
 */
export const questionTypes: Modules & Record<QuestionType, unknown> = {
    single_choice: {
        type: 'single_choice',
        icon: CircleDot,
        label: 'Single choice',
        description: 'One correct option out of several.',
        emptyInput: singleChoice.emptyInput,
        initialAnswer: singleChoice.initialAnswer,
        validate: singleChoice.validate,
        Editor: SingleChoiceEditor,
        Player: SingleChoicePlayer,
        Review: SingleChoiceReview,
    },
    multiple_choice: {
        type: 'multiple_choice',
        icon: ListChecks,
        label: 'Multiple choice',
        description: 'Several correct options, with optional partial credit.',
        emptyInput: multipleChoice.emptyInput,
        initialAnswer: multipleChoice.initialAnswer,
        validate: multipleChoice.validate,
        Editor: MultipleChoiceEditor,
        Player: MultipleChoicePlayer,
        Review: MultipleChoiceReview,
    },
    true_false: {
        type: 'true_false',
        icon: ToggleRight,
        label: 'True / false',
        description: 'Decide whether a statement is true.',
        emptyInput: trueFalse.emptyInput,
        initialAnswer: trueFalse.initialAnswer,
        validate: trueFalse.validate,
        Editor: TrueFalseEditor,
        Player: TrueFalsePlayer,
        Review: TrueFalseReview,
    },
    text: {
        type: 'text',
        icon: PencilLine,
        label: 'Text answer',
        description: 'A free answer you grade yourself.',
        emptyInput: text.emptyInput,
        initialAnswer: text.initialAnswer,
        validate: text.validate,
        Editor: TextEditor,
        Player: TextPlayer,
        Review: TextReview,
    },
    numeric: {
        type: 'numeric',
        icon: Calculator,
        label: 'Numeric answer',
        description: 'A number, with an optional tolerance and unit.',
        emptyInput: numeric.emptyInput,
        initialAnswer: numeric.initialAnswer,
        validate: numeric.validate,
        Editor: NumericEditor,
        Player: NumericPlayer,
        Review: NumericReview,
    },
    ordering: {
        type: 'ordering',
        icon: ArrowDownUp,
        label: 'Ordering',
        description: 'Put items in the right order.',
        emptyInput: ordering.emptyInput,
        initialAnswer: ordering.initialAnswer,
        validate: ordering.validate,
        Editor: OrderingEditor,
        Player: OrderingPlayer,
        Review: OrderingReview,
    },
    matching: {
        type: 'matching',
        icon: Link2,
        label: 'Matching',
        description: 'Match each entry with its partner.',
        emptyInput: matching.emptyInput,
        initialAnswer: matching.initialAnswer,
        validate: matching.validate,
        Editor: MatchingEditor,
        Player: MatchingPlayer,
        Review: MatchingReview,
    },
};

/**
 * A module without its type parameters, for pages that hold data of any type.
 * The pairing of data and type is guaranteed by the server (same `type`).
 */
export type AnyQuestionTypeModule = QuestionTypeModule<
    unknown,
    unknown,
    unknown,
    unknown
>;

export function questionTypeModule(type: QuestionType): AnyQuestionTypeModule {
    return questionTypes[type] as unknown as AnyQuestionTypeModule;
}
