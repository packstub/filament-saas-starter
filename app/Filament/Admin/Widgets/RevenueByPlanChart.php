<?php

namespace App\Filament\Admin\Widgets;

use App\Billing\RevenueMetrics;
use Filament\Widgets\ChartWidget;
use Packstub\Features\Facades\Features;

/**
 * Where the monthly recurring revenue comes from, plan by plan.
 */
class RevenueByPlanChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Monthly recurring revenue by plan';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $plans = RevenueMetrics::mrrByPlan();

        return [
            'datasets' => [[
                'label' => 'MRR ('.strtoupper((string) config('cashier.currency')).')',
                'data' => array_map(fn (int $cents): float => round($cents / 100, 2), array_values($plans)),
                'backgroundColor' => '#6366f1',
            ]],
            'labels' => array_map(fn (string $plan): string => Features::planDefinition($plan)?->label ?? $plan, array_keys($plans)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
