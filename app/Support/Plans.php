<?php

namespace App\Support;

use App\Filament\App\Pages\Billing;
use App\Models\Project;
use App\Models\Workspace;
use Packstub\Features\FeaturesPlugin;
use Packstub\Teams\Facades\Teams;

/**
 * Filament Features for both panels. The features and plans are in
 * config/packstub-features.php; this adds what a config file cannot hold:
 * how much of each limit a workspace has used, and where "Upgrade" leads.
 *
 * Both panels use the same definition on purpose: console commands, queued
 * jobs and webhooks see the settings of the panel registered last.
 */
final class Plans
{
    public static function plugin(): FeaturesPlugin
    {
        return FeaturesPlugin::make()
            // Counted where the projects are. It is asked for inside a workspace's own requests (the
            // policy, the dashboard, the plan page); code that needs it elsewhere runs in that workspace.
            ->usage('projects', fn (?Workspace $workspace): int => $workspace ? Project::query()->whereBelongsTo($workspace)->count() : 0)
            ->usage('seats', fn (?Workspace $workspace): int => $workspace ? Teams::seatsUsed($workspace) : 0)
            ->upgradeUrl(fn (?Workspace $workspace): ?string => $workspace ? Billing::getUrl(panel: 'app', tenant: $workspace) : null);
    }
}
