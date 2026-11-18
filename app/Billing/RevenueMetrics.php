<?php

namespace App\Billing;

use App\Enums\AuditEventType;
use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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

    /** Subscriptions that never started: their first payment was never made. */
    public const NEVER_PAID_STATUSES = ['incomplete', 'incomplete_expired'];

    /**
     * Monthly recurring revenue in the currency's smallest unit (cents): every
     * paying subscription's price, a yearly one divided by 12, times its
     * quantity on a per-seat plan. Trials (Stripe status `trialing`) are not
     * revenue yet; a cancelled subscription counts until its paid period ends.
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
            $byPlan = self::payingByPrice()->groupBy(fn (object $row): string => (string) StripePrices::plan($row->stripe_price));

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
     * Subscriptions being paid for now, on a price config/billing.php sells:
     * the same subscriptions MRR is made of.
     */
    public static function activeSubscriptions(): int
    {
        return self::remember('active', fn (): int => (int) self::payingByPrice()
            ->filter(fn (object $row): bool => StripePrices::plan($row->stripe_price) !== null)
            ->sum('subscriptions'));
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
     * Paying subscriptions that ended in the last `$days` days: cancelled and
     * past their paid period, or stopped by Stripe. A trial that ended without
     * a payment is not churn (it never paid), and a cancellation that takes
     * effect later is not churn yet.
     */
    public static function churned(int $days = 30): int
    {
        return self::remember("churned.{$days}", fn (): int => self::endedAfterPaying(now()->subDays($days))->count());
    }

    /**
     * Of the subscriptions that were paying `$days` days ago, the share that
     * has ended since, as a percentage, or null with none to measure.
     * Subscriptions that started within the window are left out of both sides.
     */
    public static function churnRate(int $days = 30): ?float
    {
        return self::remember("churn-rate.{$days}", function () use ($days): ?float {
            $start = now()->subDays($days);
            $base = self::payingAt($start)->count();
            $lost = self::endedAfterPaying($start)->where('created_at', '<=', $start)->count();

            return $base === 0 ? null : round($lost / $base * 100, 1);
        });
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
     * A group of subscriptions on one price as a share of a month, in cents. A
     * price no plan is sold at counts as 0 (and the webhook has logged it already).
     */
    private static function monthlyAmount(object $row): float
    {
        $plan = StripePrices::plan($row->stripe_price);
        $interval = StripePrices::interval($row->stripe_price);

        if ($plan === null) {
            return 0;
        }

        $amount = (int) config("billing.plans.{$plan}.{$interval}.unit_amount", 0);
        $units = StripePrices::perSeat($plan) ? (int) $row->seats : (int) $row->subscriptions;

        return ($interval === 'yearly' ? $amount / 12 : $amount) * $units;
    }

    /**
     * The subscriptions paid for now, added up per price in the database:
     * how many there are and how many seats they hold.
     *
     * @return Collection<int, object{stripe_price: ?string, subscriptions: int, seats: int}>
     */
    private static function payingByPrice(): Collection
    {
        return Subscription::query()
            ->toBase()
            ->whereIn('stripe_status', self::PAYING_STATUSES)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->groupBy('stripe_price')
            ->selectRaw('stripe_price, count(*) as subscriptions, sum(case when quantity is null or quantity < 1 then 1 else quantity end) as seats')
            ->get();
    }

    /**
     * Subscriptions that were paying at a moment: started, not yet ended, past any trial.
     *
     * @return Builder<Subscription>
     */
    private static function payingAt(CarbonInterface $moment): Builder
    {
        return Subscription::query()
            ->whereNotIn('stripe_status', self::NEVER_PAID_STATUSES)
            ->where('created_at', '<=', $moment)
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $moment))
            ->where(fn ($query) => $query->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<=', $moment));
    }

    /**
     * Subscriptions that ended between a moment and now, after paying at least once:
     * any trial they had was over before they ended.
     *
     * @return Builder<Subscription>
     */
    private static function endedAfterPaying(CarbonInterface $since): Builder
    {
        return Subscription::query()
            ->whereNotIn('stripe_status', self::NEVER_PAID_STATUSES)
            ->whereBetween('ends_at', [$since, now()])
            ->where(fn ($query) => $query->whereNull('trial_ends_at')->orWhereColumn('trial_ends_at', '<', 'ends_at'));
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
