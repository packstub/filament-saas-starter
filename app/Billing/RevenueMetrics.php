<?php

namespace App\Billing;

use App\Enums\AuditEventType;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Number;
use Laravel\Cashier\Subscription;

/**
 * The numbers on the admin dashboard, read from the app's own database:
 * Cashier's `subscriptions` table for revenue, `workspaces` and `users` for
 * growth, the audit log for plan changes. Nothing asks Stripe, so the dashboard opens fast and works
 * without a Stripe account; the price behind a subscription is turned into
 * money with `unit_amount` in config/billing.php.
 *
 * Each figure is kept for a few minutes (`CACHE_SECONDS`), so a busy
 * dashboard runs the queries once.
 */
final class RevenueMetrics
{
    public const CACHE_SECONDS = 300;

    /** The Stripe statuses of a subscription that is paid for: active, or in Stripe's payment retries. */
    public const PAYING_STATUSES = ['active', 'past_due'];

    /**
     * Monthly recurring revenue in the currency's smallest unit (cents): every
     * paying subscription's price, a yearly one divided by 12, times its
     * quantity on a per-seat plan. Trials are not revenue yet; a cancelled
     * subscription counts until its paid period ends.
     */
    public static function mrr(): int
    {
        return array_sum(self::mrrByPlan());
    }

    /**
     * MRR per paid plan, in cents, as plan key => amount, every plan sold in
     * config/billing.php listed.
     *
     * @return array<string, int>
     */
    public static function mrrByPlan(): array
    {
        return self::remember('mrr-by-plan', function (): array {
            $byPlan = self::paying()->groupBy(fn (Subscription $subscription): string => (string) StripePrices::plan($subscription->stripe_price));

            return collect(config('billing.plans', []))
                ->map(fn (array $plan, string $key): int => (int) round(($byPlan[$key] ?? collect())->sum(self::monthlyAmount(...))))
                ->all();
        });
    }

    /**
     * MRR as people read it: "$1,234.50".
     */
    public static function format(int $cents): string
    {
        return Number::currency($cents / 100, in: strtoupper((string) config('cashier.currency', 'usd')));
    }

    /**
     * Subscriptions being paid for now.
     */
    public static function activeSubscriptions(): int
    {
        return self::remember('active', fn (): int => self::paying()->count());
    }

    /**
     * Subscriptions in their free trial.
     */
    public static function trials(): int
    {
        return self::remember('trials', fn (): int => Subscription::query()
            ->where('stripe_status', 'trialing')
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->count());
    }

    /**
     * Subscriptions that ended in the last `$days` days: cancelled and past
     * their paid period, or stopped by Stripe.
     */
    public static function churned(int $days = 30): int
    {
        return self::remember("churned.{$days}", fn (): int => Subscription::query()
            ->whereBetween('ends_at', [now()->subDays($days), now()])
            ->count());
    }

    /**
     * The share of the subscriptions paid for `$days` days ago that have
     * ended since, as a percentage, or null with none to measure.
     */
    public static function churnRate(int $days = 30): ?float
    {
        $churned = self::churned($days);
        $base = self::activeSubscriptions() + $churned;

        return $base === 0 ? null : round($churned / $base * 100, 1);
    }

    /**
     * How many workspaces are on each plan, as plan key => count, every plan
     * in config/packstub-features.php listed.
     *
     * @return array<string, int>
     */
    public static function planDistribution(): array
    {
        return self::remember('plans', function (): array {
            $counts = Workspace::query()
                ->toBase()
                ->selectRaw('plan, count(*) as aggregate')
                ->groupBy('plan')
                ->pluck('aggregate', 'plan')
                ->map(fn (mixed $count): int => (int) $count);

            return collect(config('packstub-features.plans'))
                ->map(fn (mixed $plan, string $key): int => $counts[$key] ?? 0)
                ->all();
        });
    }

    /**
     * New rows per day over the last `$days` days, oldest first, as
     * 'Y-m-d' => count; days without any are 0.
     *
     * @param  class-string<User|Workspace>  $model
     * @return array<string, int>
     */
    public static function perDay(string $model, int $days = 30): array
    {
        return self::remember("per-day.{$model}.{$days}", function () use ($model, $days): array {
            $from = now()->subDays($days - 1)->startOfDay();

            $counts = $model::query()
                ->toBase()
                ->where('created_at', '>=', $from)
                ->selectRaw('date(created_at) as day, count(*) as aggregate')
                ->groupBy('day')
                ->pluck('aggregate', 'day');

            return collect(range(0, $days - 1))
                ->mapWithKeys(fn (int $offset): array => [$day = $from->copy()->addDays($offset)->toDateString() => (int) ($counts[$day] ?? 0)])
                ->all();
        });
    }

    /**
     * Plan changes in the last `$days` days, upgrades and downgrades alike,
     * from the workspaces' audit log.
     */
    public static function planChanges(int $days = 30): int
    {
        return self::remember("plan-changes.{$days}", fn (): int => AuditEvent::query()
            ->where('event', AuditEventType::PlanChanged)
            ->where('created_at', '>=', now()->subDays($days))
            ->count());
    }

    /**
     * The subscription's share of a month, in cents. A price no plan is sold
     * at counts as 0 (and the webhook has logged it already).
     */
    private static function monthlyAmount(Subscription $subscription): float
    {
        $plan = StripePrices::plan($subscription->stripe_price);
        $interval = StripePrices::interval($subscription->stripe_price);

        if ($plan === null) {
            return 0;
        }

        $amount = (int) config("billing.plans.{$plan}.{$interval}.unit_amount", 0);
        $quantity = StripePrices::perSeat($plan) ? max(1, (int) $subscription->quantity) : 1;

        return ($interval === 'yearly' ? $amount / 12 : $amount) * $quantity;
    }

    /**
     * @return Collection<int, Subscription>
     */
    private static function paying(): Collection
    {
        return Subscription::query()
            ->whereIn('stripe_status', self::PAYING_STATUSES)
            ->where(fn ($query) => $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', now()))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->get(['id', 'stripe_price', 'quantity']);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private static function remember(string $key, callable $callback): mixed
    {
        return Cache::remember("revenue-metrics.{$key}", self::CACHE_SECONDS, $callback);
    }
}
