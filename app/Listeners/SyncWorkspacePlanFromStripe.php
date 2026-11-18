<?php

namespace App\Listeners;

use App\Models\Workspace;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Stripe is the source of truth for what a workspace pays for. Cashier has
 * already written the subscription to the database when handle() runs, so
 * every subscription webhook (created, updated, deleted) ends with the
 * workspace on the matching plan.
 */
class SyncWorkspacePlanFromStripe
{
    public function handle(WebhookHandled $event): void
    {
        if (! str_starts_with($event->payload['type'] ?? '', 'customer.subscription.')) {
            return;
        }

        $workspace = Cashier::findBillable($event->payload['data']['object']['customer'] ?? null);

        if ($workspace instanceof Workspace) {
            $workspace->syncPlanFromSubscription();

            // A per-seat subscription whose quantity drifted from the members (a sync
            // that failed, a change in the Stripe dashboard) is put right here.
            $workspace->syncSeatQuantity();
        }
    }

    /**
     * A customer deleted in Stripe takes its subscriptions with it. Cashier
     * then clears the workspace's `stripe_id`, after which no later webhook
     * can find the workspace, so the plan goes back to the default one here,
     * before Cashier handles the event.
     */
    public function handleCustomerDeleted(WebhookReceived $event): void
    {
        if (($event->payload['type'] ?? null) !== 'customer.deleted' || RefuseUnsignedStripeWebhooks::refuses()) {
            return;
        }

        $workspace = Cashier::findBillable($event->payload['data']['object']['id'] ?? null);

        if ($workspace instanceof Workspace) {
            $workspace->changePlan(config('packstub-features.default_plan'));
        }
    }
}
