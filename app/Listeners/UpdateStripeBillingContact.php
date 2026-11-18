<?php

namespace App\Listeners;

use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Packstub\Teams\Events\OwnershipTransferred;
use Stripe\Exception\ApiErrorException;

/**
 * Ownership moved to another member ("Transfer ownership" on the Members
 * page): Stripe's receipts and billing e-mails follow the new owner
 * (Workspace::stripeEmail() is the first owner's address). Queued, once the
 * change is committed; a refused call is reported and retried.
 */
class UpdateStripeBillingContact implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 5;

    public function handle(OwnershipTransferred $event): void
    {
        $workspace = $event->team;

        if (! $workspace instanceof Workspace || ! $workspace->hasStripeId() || blank(config('cashier.secret'))) {
            return;
        }

        try {
            $workspace->syncStripeCustomerDetails();
        } catch (ApiErrorException $exception) {
            report($exception);

            $this->release(60);
        }
    }
}
