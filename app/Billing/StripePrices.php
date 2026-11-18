<?php

namespace App\Billing;

/**
 * The link between the plans in config/packstub-features.php and the Stripe
 * prices in config/billing.php: each paid plan is sold monthly, yearly or
 * both, and every one of those prices maps back to its plan.
 */
final class StripePrices
{
    /** The billing intervals a plan can be sold at; the keys matter, labels() gives the words people read. */
    public const INTERVALS = [
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ];

    /**
     * The intervals with their labels in the current language, as interval => label.
     *
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return array_map(fn (string $interval): string => self::label($interval), array_combine(array_keys(self::INTERVALS), array_keys(self::INTERVALS)));
    }

    /**
     * The label of one interval in the current language ("Monthly"); the key itself for an unknown one.
     */
    public static function label(string $interval): string
    {
        return array_key_exists($interval, self::INTERVALS) ? __("billing.intervals.{$interval}") : $interval;
    }

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
     * The Stripe price a plan is sold at for an interval, or null when the
     * plan is not sold at that interval (or not sold at all).
     */
    public static function for(string $plan, string $interval = 'monthly'): ?string
    {
        return self::prices()[$plan][$interval] ?? null;
    }

    /**
     * What people see for a plan at an interval ("$29/mo"). Shown before the
     * Stripe price is set too, so a new install still shows its pricing.
     */
    public static function amount(string $plan, string $interval): ?string
    {
        return config("billing.plans.{$plan}.{$interval}.amount");
    }

    /**
     * Whether a plan is billed per member (the subscription's quantity) instead of at a flat price.
     */
    public static function perSeat(?string $plan): bool
    {
        return $plan !== null && (bool) config("billing.plans.{$plan}.per_seat", false);
    }

    /**
     * The plan a Stripe price belongs to, or null for a price no plan is sold at.
     */
    public static function plan(?string $price): ?string
    {
        return self::find($price)['plan'] ?? null;
    }

    /**
     * The interval a Stripe price bills at (`monthly`, `yearly`), or null for a price no plan is sold at.
     */
    public static function interval(?string $price): ?string
    {
        return self::find($price)['interval'] ?? null;
    }

    /**
     * The intervals at least one plan is sold at, in the order of INTERVALS.
     *
     * @return list<string>
     */
    public static function intervals(): array
    {
        $sold = array_merge([], ...array_values(array_map(array_keys(...), self::prices())));

        return array_values(array_intersect(array_keys(self::INTERVALS), $sold));
    }

    /**
     * @return array{plan: string, interval: string}|null
     */
    private static function find(?string $price): ?array
    {
        if (blank($price)) {
            return null;
        }

        foreach (self::prices() as $plan => $intervals) {
            if (($interval = array_search($price, $intervals, true)) !== false) {
                return ['plan' => $plan, 'interval' => $interval];
            }
        }

        return null;
    }

    /**
     * Every price that is set, as plan => interval => price ID.
     *
     * @return array<string, array<string, string>>
     */
    private static function prices(): array
    {
        return collect(config('billing.plans', []))
            ->map(fn (array $intervals): array => collect(self::INTERVALS)
                ->keys()
                ->mapWithKeys(fn (string $interval): array => [$interval => $intervals[$interval]['price'] ?? null])
                ->filter(fn (?string $price): bool => filled($price))
                ->all())
            ->filter()
            ->all();
    }
}
