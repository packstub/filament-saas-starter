<?php

namespace App\Listeners;

use App\Enums\AuditEventType;
use App\Models\ApiToken;
use App\Models\AuditEvent;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Packstub\Teams\Events\MemberRemoved;
use Packstub\Teams\Events\MemberRoleChanged;
use Packstub\Teams\Facades\Teams;

/**
 * Someone who created API tokens left the workspace, or is no longer an
 * owner or admin: their tokens are revoked. They saw each token in full when
 * they created it, so a token of theirs would otherwise keep the workspace's
 * data in reach of someone who no longer may manage it. Account deletion
 * removes the person from every workspace first, so it is covered too.
 */
class RevokeApiTokensOfFormerManagers
{
    public function handle(MemberRemoved|MemberRoleChanged $event): void
    {
        if (! $event->team instanceof Workspace) {
            return;
        }

        if ($event instanceof MemberRoleChanged && Teams::canManage($event->user, $event->team)) {
            return;
        }

        $this->revoke($event->team, $event->user);
    }

    protected function revoke(Workspace $workspace, Model $user): void
    {
        $workspace->tokens()
            ->where('created_by_id', $user->getKey())
            ->get()
            ->each(function (ApiToken $token) use ($workspace, $user): void {
                $token->delete();

                // `from_id`/`from`, so AuditEvent::forget() takes the name out if they delete their account.
                AuditEvent::record($workspace, AuditEventType::ApiTokenRevoked, [
                    'name' => $token->name,
                    'from_id' => $user->getKey(),
                    'from' => $user->getAttribute('name'),
                ]);
            });
    }
}
