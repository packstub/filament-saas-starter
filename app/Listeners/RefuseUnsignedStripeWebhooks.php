<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Cashier checks a webhook's signature only when STRIPE_WEBHOOK_SECRET is
 * set. Without it anyone could post a "subscription updated" event and put
 * a workspace on a paid plan, so in production such a request is refused
 * before Cashier acts on it. Stripe shows the failed deliveries and sends
 * them again for days, so nothing is lost once the secret is in place.
 */
class RefuseUnsignedStripeWebhooks
{
    /**
     * Whether a webhook arriving now is one nobody can vouch for. Other
     * listeners of WebhookReceived ask this too: listeners run in no set order.
     */
    public static function refuses(): bool
    {
        return app()->isProduction() && blank(config('cashier.webhook.secret'));
    }

    public function handle(WebhookReceived $event): void
    {
        if (! static::refuses()) {
            return;
        }

        Log::error('A Stripe webhook was refused because STRIPE_WEBHOOK_SECRET is not set. Set it to the signing secret of the webhook endpoint.', [
            'type' => $event->payload['type'] ?? null,
        ]);

        abort(403, 'Webhook signatures are not configured.');
    }
}
