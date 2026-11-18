<?php

namespace App\Listeners;

use App\Jobs\SyncStripeCustomer;
use App\Models\Workspace;
use Packstub\Teams\Events\OwnershipTransferred;

/**
 * Ownership moved to another member ("Transfer ownership" on the Members
 * page): Stripe's receipts and billing e-mails follow the new owner. Not
 * queued itself, for the reason given in SyncSeatQuantityWithStripe; it
 * queues SyncStripeCustomer, which holds the workspace's id only.
 */
class UpdateStripeBillingContact
{
    public function handle(OwnershipTransferred $event): void
    {
        if ($event->team instanceof Workspace && $event->team->hasStripeId()) {
            SyncStripeCustomer::dispatch($event->team)->afterCommit();
        }
    }
}
