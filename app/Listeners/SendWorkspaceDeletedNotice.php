<?php

namespace App\Listeners;

use App\Events\WorkspaceDeleting;
use App\Models\User;
use App\Notifications\WorkspaceDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Packstub\Teams\Facades\Teams;

/**
 * Tell everyone who was in a deleted workspace, once the deletion is
 * committed. The members are read now, while they are still there; an
 * account deleted in the same transaction (the person deleting their own
 * account) gets the account's confirmation instead.
 */
class SendWorkspaceDeletedNotice
{
    public function handle(WorkspaceDeleting $event): void
    {
        $workspace = $event->workspace->name;
        $user = auth()->user();
        $deletedBy = $user instanceof User ? $user->name : null;
        $members = Teams::membersOf($event->workspace)->pluck('name', 'email')->all();

        DB::afterCommit(function () use ($workspace, $deletedBy, $members): void {
            $stillHere = User::query()->whereIn('email', array_keys($members))->pluck('email')->all();

            foreach ($stillHere as $email) {
                Notification::route('mail', [$email => $members[$email]])
                    ->notify(new WorkspaceDeleted($workspace, $deletedBy));
            }
        });
    }
}
