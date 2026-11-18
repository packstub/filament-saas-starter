<?php

namespace App\Listeners;

use App\Enums\AuditEventType;
use App\Models\AuditEvent;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Packstub\Teams\Events\InvitationAccepted;
use Packstub\Teams\Events\MemberAdded;
use Packstub\Teams\Events\MemberInvited;
use Packstub\Teams\Events\MemberRemoved;
use Packstub\Teams\Events\MemberRoleChanged;
use Packstub\Teams\Events\OwnershipTransferred;
use Packstub\Teams\Facades\Teams;

/**
 * Filament Teams' membership events into the workspace's audit log. Not
 * queued: the entry is written in the same request, while who did it is
 * still known. Plan changes and deletions are written by Workspace itself.
 */
class RecordWorkspaceActivity
{
    public function handle(MemberAdded|MemberRemoved|MemberRoleChanged|MemberInvited|InvitationAccepted|OwnershipTransferred $event): void
    {
        match (true) {
            $event instanceof MemberAdded => $this->record($event->team, AuditEventType::MemberAdded, [
                ...$this->person($event->user),
                'role' => $this->role($event->team, $event->role),
            ]),
            $event instanceof MemberRemoved => $this->record($event->team, AuditEventType::MemberRemoved, $this->person($event->user)),
            $event instanceof MemberRoleChanged => $this->record($event->team, AuditEventType::RoleChanged, [
                ...$this->person($event->user),
                'from' => $this->role($event->team, $event->from),
                'to' => $this->role($event->team, $event->to),
            ]),
            // An e-mail invitation, or a shareable link (no e-mail).
            $event instanceof MemberInvited => $this->record($event->invitation->team, AuditEventType::InvitationSent, array_filter([
                'email' => $event->invitation->email,
                'role' => $this->role($event->invitation->team, $event->invitation->role),
            ])),
            $event instanceof InvitationAccepted => $this->record($event->invitation->team, AuditEventType::InvitationAccepted, $this->person($event->user)),
            $event instanceof OwnershipTransferred => $this->record($event->team, AuditEventType::OwnershipTransferred, [
                'from_id' => $event->from->getKey(),
                'from' => $event->from->getAttribute('name'),
                'to_id' => $event->to->getKey(),
                'to' => $event->to->getAttribute('name'),
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function record(?Model $workspace, AuditEventType $event, array $properties): void
    {
        if ($workspace instanceof Workspace) {
            AuditEvent::record($workspace, $event, $properties);
        }
    }

    /**
     * @return array{user_id: mixed, name: mixed, email: mixed}
     */
    protected function person(Model $user): array
    {
        return ['user_id' => $user->getKey(), 'name' => $user->getAttribute('name'), 'email' => $user->getAttribute('email')];
    }

    protected function role(Model $workspace, string $role): string
    {
        return Teams::roleLabel($workspace, $role);
    }
}
