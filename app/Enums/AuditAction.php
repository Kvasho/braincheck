<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * What an audit log entry records. Keys are namespaced by area ("area.event")
 * and stored verbatim in audit_logs.action, so existing values never change.
 */
enum AuditAction: string implements HasLabel
{
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case PasswordChanged = 'auth.password_changed';
    case PasswordReset = 'auth.password_reset';
    case TwoFactorEnabled = 'auth.two_factor_enabled';
    case TwoFactorDisabled = 'auth.two_factor_disabled';
    case RecoveryCodesRegenerated = 'auth.recovery_codes_regenerated';

    case UserRoleChanged = 'user.role_changed';
    case UserBlocked = 'user.blocked';
    case UserUnblocked = 'user.unblocked';

    case TeacherRequestSubmitted = 'teacher_request.submitted';
    case TeacherRequestApproved = 'teacher_request.approved';
    case TeacherRequestRejected = 'teacher_request.rejected';

    case CategoryCreated = 'category.created';
    case CategoryUpdated = 'category.updated';
    case CategoryDeleted = 'category.deleted';

    case TagCreated = 'tag.created';
    case TagUpdated = 'tag.updated';
    case TagDeleted = 'tag.deleted';
    case TagMerged = 'tag.merged';

    case GroupJoinFailed = 'group.join_failed';

    case GradingOverride = 'grading.override';
    case GradingRegrade = 'grading.regrade';
    case GradingIdentityRevealed = 'grading.identity_revealed';

    case TestResultsReleased = 'test.results_released';

    public function label(): string
    {
        return match ($this) {
            self::Login => __('Signed in'),
            self::LoginFailed => __('Failed sign-in'),
            self::Logout => __('Signed out'),
            self::PasswordChanged => __('Password changed'),
            self::PasswordReset => __('Password reset'),
            self::TwoFactorEnabled => __('Two-factor authentication enabled'),
            self::TwoFactorDisabled => __('Two-factor authentication disabled'),
            self::RecoveryCodesRegenerated => __('Recovery codes regenerated'),
            self::UserRoleChanged => __('Role changed'),
            self::UserBlocked => __('User blocked'),
            self::UserUnblocked => __('User unblocked'),
            self::TeacherRequestSubmitted => __('Teacher access requested'),
            self::TeacherRequestApproved => __('Teacher request approved'),
            self::TeacherRequestRejected => __('Teacher request rejected'),
            self::CategoryCreated => __('Category created'),
            self::CategoryUpdated => __('Category updated'),
            self::CategoryDeleted => __('Category deleted'),
            self::TagCreated => __('Tag created'),
            self::TagUpdated => __('Tag updated'),
            self::TagDeleted => __('Tag deleted'),
            self::TagMerged => __('Tags merged'),
            self::GroupJoinFailed => __('Failed group join'),
            self::GradingOverride => __('Score overridden'),
            self::GradingRegrade => __('Question regraded'),
            self::GradingIdentityRevealed => __('Student identity revealed while grading'),
            self::TestResultsReleased => __('Test results released'),
        };
    }
}
