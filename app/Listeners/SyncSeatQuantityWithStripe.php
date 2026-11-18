<?php

namespace App\Listeners;

use App\Models\Workspace;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Packstub\Teams\Events\MemberAdded;
use Packstub\Teams\Events\MemberRemoved;
use Stripe\Exception\ApiErrorException;

/**
 * Someone joined or left a workspace: on a per-seat plan, the Stripe
 * subscription's quantity follows (Workspace::syncSeatQuantity()). An
 * accepted invitation adds a member too, so it is counted here as well.
 *
 * Queued, once the membership is committed, so a slow or failing Stripe
 * never holds up the Members page. A failed call is reported and retried a
 * minute later; the next subscription webhook also puts the quantity right
 * (App\Listeners\SyncWorkspacePlanFromStripe).
 */
class SyncSeatQuantityWithStripe implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 5;

    public function handle(MemberAdded|MemberRemoved $event): void
    {
        if (! $event->team instanceof Workspace) {
            return;
        }

        try {
            $event->team->syncSeatQuantity();
        } catch (ApiErrorException $exception) {
            report($exception);

            $this->release(60);
        }
    }
}
