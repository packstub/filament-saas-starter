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
            Stat::make('Monthly recurring revenue', RevenueMetrics::format($mrr))
                ->description(RevenueMetrics::format($mrr * 12).' a year')
                ->icon(Heroicon::OutlinedBanknotes),
            Stat::make('Paying subscriptions', RevenueMetrics::activeSubscriptions())
                ->description('Active or retrying a payment')
                ->icon(Heroicon::OutlinedCreditCard),
            Stat::make('On a free trial', RevenueMetrics::trials())
                ->description('Not counted in revenue yet')
                ->icon(Heroicon::OutlinedGift),
            Stat::make('Churned, last 30 days', RevenueMetrics::churned())
                ->description($rate === null ? 'No subscriptions yet' : $rate.'% of subscriptions')
                ->descriptionColor($rate ? 'danger' : 'gray')
                ->icon(Heroicon::OutlinedArrowTrendingDown),
        ];
    }
}
