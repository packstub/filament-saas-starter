<?php

namespace App\Filament\Admin\Widgets;

use App\Billing\RevenueMetrics;
use Filament\Widgets\ChartWidget;
use Packstub\Features\Facades\Features;

/**
 * How many workspaces are on each plan.
 */
class PlanDistributionChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('ui_admin.plan_distribution.heading');
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $plans = RevenueMetrics::planDistribution();

        return [
            'datasets' => [[
                'label' => __('ui_admin.plan_distribution.dataset'),
                'data' => array_values($plans),
                'backgroundColor' => array_slice(['#94a3b8', '#6366f1', '#14b8a6', '#f59e0b', '#ec4899', '#0ea5e9'], 0, count($plans)),
            ]],
            'labels' => array_map(fn (string $plan): string => Features::planDefinition($plan)?->label ?? $plan, array_keys($plans)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
