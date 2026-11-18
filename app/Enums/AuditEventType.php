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
    case WorkspaceDeleted = 'workspace.deleted';

    public function getLabel(): string
    {
        return match ($this) {
            self::MemberAdded => 'Member added',
            self::MemberRemoved => 'Member removed',
            self::RoleChanged => 'Role changed',
            self::InvitationSent => 'Invitation sent',
            self::InvitationAccepted => 'Invitation accepted',
            self::OwnershipTransferred => 'Ownership transferred',
            self::PlanChanged => 'Plan changed',
            self::WorkspaceDeleted => 'Workspace deleted',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MemberAdded, self::InvitationAccepted => 'success',
            self::MemberRemoved, self::WorkspaceDeleted => 'danger',
            self::OwnershipTransferred, self::PlanChanged => 'warning',
            self::RoleChanged, self::InvitationSent => 'gray',
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
            self::WorkspaceDeleted => Heroicon::OutlinedTrash,
        };
    }
}
