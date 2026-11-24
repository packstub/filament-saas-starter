<?php

namespace App\Support;

use App\Billing\StripePrices;
use Illuminate\Support\Facades\Lang;
use Packstub\Features\Facades\Features;
use Packstub\Features\Support\Plan;

/**
 * The plans as people read them, for the pricing section of the home page:
 * every plan in config/packstub-features.php, what it includes, and the
 * amounts from config/billing.php.
 *
 * Once Stripe prices are set, a plan shows only the intervals it is sold at,
 * exactly as the Billing page does. Before any price is set (a new project,
 * a local copy) every `amount` in config/billing.php shows, so the page
 * reads the same as it will in production.
 */
final class Pricing
{
    /**
     * @return list<array{key: string, label: string, description: ?string, amounts: array<string, string>, includes: list<string>, per_seat: bool, paid: bool}>
     */
    public static function plans(): array
    {
        return array_values(array_map(function (Plan $plan): array {
            $amounts = collect(self::intervals())
                ->mapWithKeys(fn (string $label, string $interval): array => [$interval => self::amount($plan, $interval)])
                ->filter()
                ->all();

            return [
                'key' => $plan->key,
                'label' => $plan->label,
                'description' => $plan->description,
                'amounts' => $amounts,
                'includes' => self::includes($plan),
                'per_seat' => StripePrices::perSeat($plan->key),
                'paid' => config("billing.plans.{$plan->key}") !== null,
            ];
        }, Features::plans()));
    }

    /**
     * The intervals to offer a switch between, as interval => label: those
     * at least one plan shows an amount for.
     *
     * @return array<string, string>
     */
    public static function intervals(): array
    {
        $shown = self::anyPriceSet()
            ? StripePrices::intervals()
            : collect(config('billing.plans', []))->flatMap(fn (array $plan): array => array_keys(array_filter(
                array_intersect_key($plan, StripePrices::INTERVALS),
                fn (mixed $interval): bool => filled($interval['amount'] ?? null),
            )))->unique()->all();

        return array_intersect_key(StripePrices::INTERVALS, array_flip($shown)) ?: ['monthly' => StripePrices::INTERVALS['monthly']];
    }

    /**
     * Days of free trial on a first subscription, 0 for none.
     */
    public static function trialDays(): int
    {
        return max(0, (int) config('billing.trial_days'));
    }

    /**
     * What a plan includes, one line per feature: "50 projects", "Unlimited seats", "Priority support".
     * The lines are translated (the pricing file in lang, by feature key); a feature
     * you add without a line there reads as its label and unit from config.
     *
     * @return list<string>
     */
    public static function includes(Plan $plan): array
    {
        $lines = [];

        foreach (Features::features() as $feature) {
            $value = $feature->cast($plan->has($feature->key) ? $plan->value($feature->key) : $feature->default);

            if ($feature->isLimit()) {
                $lines[] = self::limitLine($feature->key, $value, $feature->unit ?? $feature->label);
            } elseif ($value) {
                $lines[] = Lang::has("ui_pricing.features.{$feature->key}") ? __("ui_pricing.features.{$feature->key}") : $feature->label;
            }
        }

        return $lines;
    }

    /**
     * "50 projects", or "Unlimited projects" when the limit is null.
     */
    private static function limitLine(string $key, ?int $value, string $fallbackUnit): string
    {
        if ($value === null) {
            return Lang::has("ui_pricing.unlimited_limits.{$key}")
                ? __("ui_pricing.unlimited_limits.{$key}")
                : __('ui_pricing.unlimited', ['unit' => $fallbackUnit]);
        }

        return Lang::has("ui_pricing.limits.{$key}")
            ? trans_choice("ui_pricing.limits.{$key}", $value, ['count' => $value])
            : "{$value} {$fallbackUnit}";
    }

    /**
     * "$29/mo" for a paid plan at an interval, the plan's own `price` text
     * ("$0") for one that is not sold, or null when it is not offered so.
     */
    private static function amount(Plan $plan, string $interval): ?string
    {
        if (config("billing.plans.{$plan->key}") === null) {
            return $plan->price;
        }

        if (self::anyPriceSet() && StripePrices::for($plan->key, $interval) === null) {
            return null;
        }

        return StripePrices::amount($plan->key, $interval);
    }

    private static function anyPriceSet(): bool
    {
        return StripePrices::intervals() !== [];
    }
}
