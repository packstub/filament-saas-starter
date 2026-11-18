<?php

namespace App\Listeners;

use App\Models\Workspace;
use App\Notifications\PaymentFailed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Events\WebhookHandled;
use Packstub\Teams\Facades\Teams;

/**
 * Tell a workspace's owners when its subscription falls past due: Stripe
 * could not collect a renewal. Once per failure, on the webhook that moves
 * the subscription into `past_due`, not on every retry after it, and once
 * per event however often Stripe delivers it.
 *
 * Stripe can also e-mail the customer about failed payments (its customer
 * e-mail settings); this one comes from the app and links to the Billing page.
 */
class NotifyOwnersOfFailedPayment
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $subscription = $payload['data']['object'] ?? [];
        $before = $payload['data']['previous_attributes']['status'] ?? null;

        if (($payload['type'] ?? null) !== 'customer.subscription.updated'
            || ($subscription['status'] ?? null) !== 'past_due'
            || $before === null || $before === 'past_due') {
            return;
        }

        $workspace = Cashier::findBillable($subscription['customer'] ?? null);

        // Stripe delivers an event at least once, and again when it thinks a delivery failed:
        // the same event never notifies twice. Three days outlast Stripe's retries.
        if ($workspace instanceof Workspace && Cache::add('stripe-event:'.($payload['id'] ?? '').':payment-failed', true, now()->addDays(3))) {
            Notification::send(Teams::ownersOf($workspace)->get(), new PaymentFailed($workspace));
        }
    }
}
