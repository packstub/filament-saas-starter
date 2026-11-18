<?php

namespace App\Listeners;

use App\Jobs\SyncSeatQuantity;
use App\Models\Workspace;
use Packstub\Teams\Events\MemberAdded;
use Packstub\Teams\Events\MemberRemoved;

/**
 * Someone joined or left a workspace: on a per-seat plan, the Stripe
 * subscription's quantity follows. An accepted invitation adds a member
 * too, so it is counted here as well.
 *
 * The listener itself is not queued: Filament Teams' events carry whole
 * User models, and a queued listener would write every column of them
 * (password hash, remember token, two-factor secret) into the queue. It
 * queues SyncSeatQuantity instead, which holds the workspace's id only,
 * once the membership is committed.
 */
class SyncSeatQuantityWithStripe
{
    public function handle(MemberAdded|MemberRemoved $event): void
    {
        if ($event->team instanceof Workspace) {
            SyncSeatQuantity::dispatch($event->team)->afterCommit();
        }
    }
}
