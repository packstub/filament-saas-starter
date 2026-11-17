<?php

namespace App\Billing;

/**
 * The link between the plans in config/packstub-features.php and the Stripe
 * prices in config/billing.php.
 */
final class StripePrices
{
    /**
     * Whether plans can be sold. Without a Stripe secret the billing page
     * explains what to set instead of offering a checkout. In production the
     * webhook secret is needed too: unsigned webhooks are refused there
     * (App\Listeners\RefuseUnsignedStripeWebhooks), and a payment whose
     * webhook never arrives would not change the plan.
     */
    public static function configured(): bool
    {
        return filled(config('cashier.secret'))
            && (filled(config('cashier.webhook.secret')) || ! app()->isProduction());
    }

    /**
     * The Stripe price a plan is sold at, or null for a plan that is not sold.
     */
    public static function for(string $plan): ?string
    {
        return config("billing.prices.{$plan}") ?: null;
    }

    /**
     * The plan a Stripe price belongs to, or null for a price no plan is sold at.
     */
    public static function plan(?string $price): ?string
    {
        $plan = $price === null ? false : array_search($price, array_filter(config('billing.prices')), true);

        return $plan === false ? null : $plan;
    }
}
