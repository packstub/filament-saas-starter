<?php

namespace App\Filament\Admin\Widgets;

use App\Billing\RevenueMetrics;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * The first row of the admin dashboard: revenue and subscriptions, from
 * the app's own database (App\Billing\RevenueMetrics), never from Stripe.
 */
class RevenueOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    // Figures are cached for a few minutes; polling would only re-read the cache.
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        $mrr = RevenueMetrics::mrr();
        $rate = RevenueMetrics::churnRate();

        return [
            Stat::make(__('ui_admin.revenue.mrr'), RevenueMetrics::format($mrr))
                ->description(__('ui_admin.revenue.per_year', ['amount' => RevenueMetrics::format($mrr * 12)]))
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make(__('ui_admin.revenue.subscriptions'), RevenueMetrics::activeSubscriptions())
                ->description(__('ui_admin.revenue.subscriptions_description'))
                ->icon(Heroicon::OutlinedCreditCard),
            Stat::make(__('ui_admin.revenue.trials'), RevenueMetrics::trials())
                ->description(__('ui_admin.revenue.trials_description'))
                ->icon(Heroicon::OutlinedGift),
            Stat::make(__('ui_admin.revenue.churned'), RevenueMetrics::churned())
                ->description($rate === null ? __('ui_admin.revenue.no_subscriptions') : __('ui_admin.revenue.churn_rate', ['rate' => $rate]))
                ->descriptionColor($rate ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArrowTrendingDown),
        ];
    }
}
