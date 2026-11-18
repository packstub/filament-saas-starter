<?php

use App\Billing\RevenueMetrics;
use App\Filament\Admin\Widgets\GrowthChart;
use App\Filament\Admin\Widgets\PlanDistributionChart;
use App\Filament\Admin\Widgets\RevenueByPlanChart;
use App\Filament\Admin\Widgets\RevenueOverview;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Cashier\Subscription;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

/**
 * A subscription row as Cashier's webhook stores it.
 */
function subscribed(string $price, string $status = 'active', array $attributes = []): Subscription
{
    return workspace()->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_'.fake()->unique()->uuid(),
        'stripe_status' => $status,
        'stripe_price' => $price,
        'quantity' => 1,
        ...$attributes,
    ]);
}

it('adds monthly and yearly prices up into monthly recurring revenue', function () {
    subscribed('price_pro');                // $29
    subscribed('price_pro_yearly');         // $290 / 12
    subscribed('price_business', 'past_due'); // $99, still being retried

    expect(RevenueMetrics::mrr())->toBe(2900 + 2417 + 9900)
        ->and(RevenueMetrics::mrrByPlan())->toBe(['pro' => 5317, 'business' => 9900])
        ->and(RevenueMetrics::activeSubscriptions())->toBe(3)
        ->and(RevenueMetrics::format(RevenueMetrics::mrr()))->toBe('$152.17');
});

it('counts a per-seat plan once per seat, a flat plan once', function () {
    config()->set('billing.plans.business.per_seat', true);

    subscribed('price_business', attributes: ['quantity' => 4]);
    subscribed('price_pro', attributes: ['quantity' => 4]);

    expect(RevenueMetrics::mrr())->toBe(4 * 9900 + 2900);
});

it('leaves out trials, ended subscriptions and prices no plan is sold at', function () {
    subscribed('price_pro', 'trialing', ['trial_ends_at' => now()->addDays(5)]);
    subscribed('price_pro', 'canceled', ['ends_at' => now()->subDay()]);
    subscribed('price_pro', 'incomplete');
    subscribed('price_pro', 'paused');
    subscribed('price_from_the_dashboard');

    expect(RevenueMetrics::mrr())->toBe(0)
        ->and(RevenueMetrics::activeSubscriptions())->toBe(1)
        ->and(RevenueMetrics::trials())->toBe(1);
});

it('keeps a cancelled subscription in revenue until its paid period ends', function () {
    subscribed('price_pro', attributes: ['ends_at' => now()->addDays(10)]);

    expect(RevenueMetrics::mrr())->toBe(2900);
});

it('counts subscriptions that ended in the last 30 days as churn', function () {
    subscribed('price_pro');
    subscribed('price_pro');
    subscribed('price_pro');
    subscribed('price_pro', 'canceled', ['ends_at' => now()->subDays(3)]);
    subscribed('price_pro', 'canceled', ['ends_at' => now()->subDays(45)]);

    expect(RevenueMetrics::churned())->toBe(1)
        ->and(RevenueMetrics::churnRate())->toBe(25.0);
});

it('has no churn rate before the first subscription', function () {
    expect(RevenueMetrics::churnRate())->toBeNull();
});

it('counts workspaces per plan, every plan listed', function () {
    workspace('pro');
    workspace('pro');
    workspace('business');

    expect(RevenueMetrics::planDistribution())->toBe(['free' => 0, 'pro' => 2, 'business' => 1]);
});

it('counts sign-ups and new workspaces per day, empty days included', function () {
    $this->travelTo(now()->startOfDay()->addHours(12));

    User::factory()->count(2)->create(['created_at' => now()->subDays(2)]);
    User::factory()->create(['created_at' => now()]);
    User::factory()->create(['created_at' => now()->subDays(40)]);
    Workspace::factory()->create(['created_at' => now()->subDay()]);

    $users = RevenueMetrics::perDay(User::class);

    expect($users)->toHaveCount(30)
        ->and(array_key_last($users))->toBe(now()->toDateString())
        ->and($users[now()->subDays(2)->toDateString()])->toBe(2)
        ->and($users[now()->toDateString()])->toBe(1)
        ->and(array_sum($users))->toBe(3)
        ->and(array_sum(RevenueMetrics::perDay(Workspace::class)))->toBe(1);
});

it('counts plan changes from the audit log', function () {
    $workspace = workspace();
    $workspace->changePlan('pro');
    $workspace->changePlan('business');

    expect(RevenueMetrics::planChanges())->toBe(2);
});

it('keeps the figures for a few minutes instead of querying on every view', function () {
    subscribed('price_pro');

    expect(RevenueMetrics::mrr())->toBe(2900);

    subscribed('price_business');

    expect(countQueries(fn () => RevenueMetrics::mrr()))->toBe(0)
        ->and(RevenueMetrics::mrr())->toBe(2900);

    $this->travel(RevenueMetrics::CACHE_SECONDS + 1)->seconds();

    expect(RevenueMetrics::mrr())->toBe(2900 + 9900);
});

it('shows the figures on the admin dashboard', function () {
    subscribed('price_pro');
    subscribed('price_pro', 'trialing', ['trial_ends_at' => now()->addDays(5)]);

    actingAs(admin());

    get('/admin')->assertOk()->assertSeeLivewire(RevenueOverview::class)->assertSeeLivewire(GrowthChart::class);

    inAdminPanel();

    livewire(RevenueOverview::class)
        ->assertSee('Monthly recurring revenue')
        ->assertSee('$29.00')
        ->assertSee('$348.00 a year')
        ->assertSee('On a free trial');
    livewire(GrowthChart::class)->assertOk()->assertSee('sign-ups');
    livewire(RevenueByPlanChart::class)->assertOk();
    livewire(PlanDistributionChart::class)->assertOk();
});

it('keeps the dashboard to admins', function () {
    actingAs(User::factory()->withAppAuthentication()->create());

    get('/admin')->assertForbidden();
});

it('reads every figure without calling Stripe', function () {
    // Any call to Stripe would fail the test: fakeStripe() is given no answers.
    fakeStripe();
    subscribed('price_pro');

    expect(RevenueMetrics::mrr())->toBe(2900);
});
