<?php

namespace App\Filament\App\Widgets;

use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Packstub\Features\Facades\Features;

/**
 * The dashboard's first row: what the workspace has used of its plan.
 */
class WorkspaceOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Plan', Features::planLabel())
                ->description($this->trialNote()),
            Stat::make('Projects', $this->usage('projects')),
            Stat::make('Seats', $this->usage('seats'))
                ->description('Members and pending invitations'),
        ];
    }

    /**
     * "Trial ends Dec 2" while the workspace's subscription is on its free trial.
     */
    protected function trialNote(): ?string
    {
        $subscription = Filament::getTenant()?->currentSubscription();

        return $subscription?->onTrial() ? 'Trial ends '.$subscription->trial_ends_at->format('M j') : null;
    }

    protected function usage(string $feature): string
    {
        $limit = Features::limit($feature);

        return Features::used($feature).($limit === null ? '' : ' of '.$limit);
    }
}
