/**
 * Mirrors the string-backed PHP enums in app/Enums used by the frontend.
 * tests/Arch/EnumParityTest.php fails if any of these drift from PHP.
 * Labels are translated on the server (Enum::label()) or via t().
 */

export const QUESTION_TYPES = [
    'single_choice',
    'multiple_choice',
    'true_false',
    'text',
    'numeric',
    'ordering',
    'matching',
] as const;
export type QuestionType = (typeof QUESTION_TYPES)[number];

export const MULTIPLE_CHOICE_SCORINGS = ['all_or_nothing', 'partial'] as const;
export type MultipleChoiceScoring = (typeof MULTIPLE_CHOICE_SCORINGS)[number];

export const DIFFICULTIES = ['easy', 'medium', 'hard'] as const;
export type Difficulty = (typeof DIFFICULTIES)[number];

export const INVITE_EXPIRIES = [
    'never',
    '1d',
    '7d',
    '30d',
    'custom',
    'keep',
] as const;
export type InviteExpiry = (typeof INVITE_EXPIRIES)[number];

export const TEST_STATUSES = ['draft', 'published', 'archived'] as const;
export type TestStatus = (typeof TEST_STATUSES)[number];

export const QUESTION_SELECTION_MODES = ['fixed', 'random_pool'] as const;
export type QuestionSelectionMode = (typeof QUESTION_SELECTION_MODES)[number];

export const RESULTS_VISIBILITIES = [
    'immediately',
    'after_close',
    'manual',
    'never',
] as const;
export type ResultsVisibility = (typeof RESULTS_VISIBILITIES)[number];

export const ATTEMPT_STATUSES = [
    'in_progress',
    'submitted',
    'timed_out',
] as const;
export type AttemptStatus = (typeof ATTEMPT_STATUSES)[number];

export const GRADING_STATUSES = [
    'pending_auto',
    'pending_manual',
    'graded',
] as const;
export type GradingStatus = (typeof GRADING_STATUSES)[number];

export const ANTI_CHEAT_EVENTS = [
    'tab_hidden',
    'window_blur',
    'copy',
    'paste',
    'fullscreen_exit',
    'devtools_suspected',
    'session_changed',
] as const;
export type AntiCheatEvent = (typeof ANTI_CHEAT_EVENTS)[number];

export const XP_REASONS = [
    'attempt_passed',
    'perfect_score',
    'first_attempt_pass',
    'badge_awarded',
    'streak',
] as const;
export type XpReason = (typeof XP_REASONS)[number];

export const BADGES = [
    'first_test',
    'perfect_score',
    'ten_tests',
    'speed_runner',
    'comeback',
    'streak_7',
    'top_10_group',
    'all_types',
] as const;
export type Badge = (typeof BADGES)[number];

export const TEACHER_REQUEST_STATUSES = [
    'pending',
    'approved',
    'rejected',
] as const;
export type TeacherRequestStatus = (typeof TEACHER_REQUEST_STATUSES)[number];

export const AUDIT_ACTIONS = [
    'auth.login',
    'auth.login_failed',
    'auth.logout',
    'auth.password_changed',
    'auth.password_reset',
    'auth.two_factor_enabled',
    'auth.two_factor_disabled',
    'auth.recovery_codes_regenerated',
    'user.role_changed',
    'user.blocked',
    'user.unblocked',
    'teacher_request.submitted',
    'teacher_request.approved',
    'teacher_request.rejected',
    'category.created',
    'category.updated',
    'category.deleted',
    'tag.created',
    'tag.updated',
    'tag.deleted',
    'tag.merged',
    'group.join_failed',
    'grading.override',
    'grading.regrade',
    'grading.identity_revealed',
    'test.results_released',
] as const;
export type AuditAction = (typeof AUDIT_ACTIONS)[number];
