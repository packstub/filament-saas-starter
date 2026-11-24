<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Filament\Support\Icons\Heroicon;

/**
 * What the `event` column of an audit event holds: what happened in a
 * workspace. Filament reads the label, colour and icon from here.
 */
enum AuditEventType: string implements HasColor, HasIcon, HasLabel
{
    case MemberAdded = 'member.added';
    case MemberRemoved = 'member.removed';
    case RoleChanged = 'member.role_changed';
    case InvitationSent = 'invitation.sent';
    case InvitationAccepted = 'invitation.accepted';
    case OwnershipTransferred = 'ownership.transferred';
    case PlanChanged = 'plan.changed';
    case ApiTokenCreated = 'api_token.created';
    case ApiTokenRevoked = 'api_token.revoked';
    case WorkspaceDeleted = 'workspace.deleted';

    public function getLabel(): string
    {
        return __('ui_activity.events.'.match ($this) {
            self::MemberAdded => 'member_added',
            self::MemberRemoved => 'member_removed',
            self::RoleChanged => 'role_changed',
            self::InvitationSent => 'invitation_sent',
            self::InvitationAccepted => 'invitation_accepted',
            self::OwnershipTransferred => 'ownership_transferred',
            self::PlanChanged => 'plan_changed',
            self::ApiTokenCreated => 'api_token_created',
            self::ApiTokenRevoked => 'api_token_revoked',
            self::WorkspaceDeleted => 'workspace_deleted',
        });
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MemberAdded, self::InvitationAccepted => 'success',
            self::MemberRemoved, self::WorkspaceDeleted => 'danger',
            self::OwnershipTransferred, self::PlanChanged => 'warning',
            self::RoleChanged, self::InvitationSent, self::ApiTokenCreated => 'gray',
            self::ApiTokenRevoked => 'danger',
        };
    }

    public function getIcon(): Heroicon
    {
        return match ($this) {
            self::MemberAdded, self::InvitationAccepted => Heroicon::OutlinedUserPlus,
            self::MemberRemoved => Heroicon::OutlinedUserMinus,
            self::RoleChanged => Heroicon::OutlinedIdentification,
            self::InvitationSent => Heroicon::OutlinedEnvelope,
            self::OwnershipTransferred => Heroicon::OutlinedArrowRightCircle,
            self::PlanChanged => Heroicon::OutlinedSparkles,
            self::ApiTokenCreated, self::ApiTokenRevoked => Heroicon::OutlinedKey,
            self::WorkspaceDeleted => Heroicon::OutlinedTrash,
        };
    }
}
