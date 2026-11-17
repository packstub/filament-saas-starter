<?php

namespace App\Filament\App\Widgets;

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
            Stat::make('Plan', Features::planLabel()),
            Stat::make('Projects', $this->usage('projects')),
            Stat::make('Seats', $this->usage('seats'))
                ->description('Members and pending invitations'),
        ];
    }

    protected function usage(string $feature): string
    {
        $limit = Features::limit($feature);

        return Features::used($feature).($limit === null ? '' : ' of '.$limit);
    }
}
