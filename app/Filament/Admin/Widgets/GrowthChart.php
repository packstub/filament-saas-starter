<?php

namespace App\Filament\Admin\Widgets;

use App\Billing\RevenueMetrics;
use App\Models\User;
use App\Models\Workspace;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

/**
 * Sign-ups and new workspaces per day over the last 30 days.
 */
class GrowthChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('ui_admin.growth.heading');
    }

    public function getDescription(): string
    {
        $users = array_sum(RevenueMetrics::perDay(User::class));
        $workspaces = array_sum(RevenueMetrics::perDay(Workspace::class));
        $changes = RevenueMetrics::planChanges();

        return __('ui_admin.growth.description', ['users' => $users, 'workspaces' => $workspaces, 'changes' => $changes]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $users = RevenueMetrics::perDay(User::class);
        $workspaces = RevenueMetrics::perDay(Workspace::class);

        return [
            'datasets' => [
                ['label' => __('ui_admin.growth.sign_ups'), 'data' => array_values($users), 'borderColor' => '#6366f1', 'backgroundColor' => '#6366f1'],
                ['label' => __('ui_admin.growth.new_workspaces'), 'data' => array_values($workspaces), 'borderColor' => '#14b8a6', 'backgroundColor' => '#14b8a6', 'borderDash' => [6, 4]],
            ],
            'labels' => array_map(fn (string $day): string => CarbonImmutable::parse($day)->translatedFormat('M j'), array_keys($users)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
