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
            Stat::make(__('ui_dashboard.overview.plan'), Features::planLabel())
                ->description($this->trialNote()),
            Stat::make(__('ui_dashboard.overview.projects'), $this->usage('projects')),
            Stat::make(__('ui_dashboard.overview.seats'), $this->usage('seats'))
                ->description(__('ui_dashboard.overview.seats_description')),
        ];
    }

    /**
     * "Trial ends Dec 2" while the workspace's subscription is on its free trial.
     */
    protected function trialNote(): ?string
    {
        $subscription = Filament::getTenant()?->currentSubscription();

        return $subscription?->onTrial() ? __('ui_dashboard.overview.trial_ends', ['date' => $subscription->trial_ends_at->translatedFormat('M j')]) : null;
    }

    protected function usage(string $feature): string
    {
        $limit = Features::limit($feature);

        return $limit === null
            ? (string) Features::used($feature)
            : __('ui_dashboard.overview.of', ['used' => Features::used($feature), 'limit' => $limit]);
    }
}
