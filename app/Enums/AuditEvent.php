<?php

namespace App\Enums;

enum AuditEvent: string
{
    case PageCreated = 'page.created';
    case PageUpdated = 'page.updated';
    case PageMoved = 'page.moved';
    case PageDeleted = 'page.deleted';
    case PageRestored = 'page.restored';
    case PageForceDeleted = 'page.force_deleted';
    case VersionRestored = 'page.version_restored';
    case SpaceCreated = 'space.created';
    case SpaceUpdated = 'space.updated';
    case SpaceDeleted = 'space.deleted';
    case SpaceContentDeleted = 'space.content_deleted';
    case SpaceContentTransferred = 'space.content_transferred';
    case SpaceMemberAdded = 'space.member_added';
    case SpaceMemberRemoved = 'space.member_removed';
    case SpaceMemberRoleChanged = 'space.member_role_changed';
    case AttachmentUploaded = 'attachment.uploaded';
    case AttachmentDeleted = 'attachment.deleted';
    case UserInvited = 'user.invited';
    case UserUpdated = 'user.updated';
    case UserDeleted = 'user.deleted';
    case PasswordResetSent = 'auth.password_reset_sent';
    case PasswordReset = 'auth.password_reset';
    case PasswordChanged = 'auth.password_changed';
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case TwoFactorEnabled = 'auth.two_factor_enabled';
    case TwoFactorDisabled = 'auth.two_factor_disabled';

    public function label(): string
    {
        return match ($this) {
            self::PageCreated => 'Page created',
            self::PageUpdated => 'Page updated',
            self::PageMoved => 'Page moved',
            self::PageDeleted => 'Page deleted',
            self::PageRestored => 'Page restored',
            self::PageForceDeleted => 'Page permanently deleted',
            self::VersionRestored => 'Version restored',
            self::SpaceCreated => 'Space created',
            self::SpaceUpdated => 'Space updated',
            self::SpaceDeleted => 'Space deleted',
            self::SpaceContentDeleted => 'Space content deleted',
            self::SpaceContentTransferred => 'Space content transferred',
            self::SpaceMemberAdded => 'Member added',
            self::SpaceMemberRemoved => 'Member removed',
            self::SpaceMemberRoleChanged => 'Member role changed',
            self::AttachmentUploaded => 'Attachment uploaded',
            self::AttachmentDeleted => 'Attachment deleted',
            self::UserInvited => 'User invited',
            self::UserUpdated => 'User updated',
            self::UserDeleted => 'User deleted',
            self::PasswordResetSent => 'Password reset sent',
            self::PasswordReset => 'Password reset',
            self::PasswordChanged => 'Password changed',
            self::Login => 'Login',
            self::LoginFailed => 'Login failed',
            self::Logout => 'Logout',
            self::TwoFactorEnabled => 'Two-factor enabled',
            self::TwoFactorDisabled => 'Two-factor disabled',
        };
    }
}
