<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Packstub\Features\Facades\Features;

use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->workspace = workspace('free');
    $this->workspace->forceFill(['stripe_id' => 'cus_test'])->save();
});

it('puts the workspace on the plan of a new subscription', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('pro')
        ->and($this->workspace->fresh()->subscribed())->toBeTrue()
        ->and(Features::limit('projects', $this->workspace->fresh()))->toBe(50)
        ->and(Features::limit('seats', $this->workspace->fresh()))->toBe(10);
});

it('follows the subscription to another plan', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();
    postJson('/stripe/webhook', stripeSubscriptionEvent('updated', $this->workspace, 'price_business'))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('business')
        ->and(Features::limit('projects', $this->workspace->fresh()))->toBeNull();
});

it('goes back to the free plan when the subscription ends', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();
    postJson('/stripe/webhook', stripeSubscriptionEvent('deleted', $this->workspace, 'price_pro', 'canceled'))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('free')
        ->and(Features::limit('projects', $this->workspace->fresh()))->toBe(3);
});

it('follows the status of the subscription', function (string $status, string $plan) {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();
    postJson('/stripe/webhook', stripeSubscriptionEvent('updated', $this->workspace, 'price_pro', $status))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe($plan);
})->with([
    'active' => ['active', 'pro'],
    'on trial' => ['trialing', 'pro'],
    // Stripe retries a failed payment for days; the customer keeps the plan while it does.
    'past due' => ['past_due', 'pro'],
    // Stripe gave up collecting.
    'unpaid' => ['unpaid', 'free'],
    'first payment not finished' => ['incomplete', 'free'],
    'first payment never made' => ['incomplete_expired', 'free'],
]);

it('stays on the free plan until a first payment succeeds', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', 'incomplete'))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('free');
});

it('keeps the paid plan until a cancelled subscription has run out', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    // Cancelled, and paid for until next week.
    $this->workspace->currentSubscription()->forceFill(['ends_at' => now()->addWeek()])->save();
    $this->workspace->syncPlanFromSubscription();

    expect($this->workspace->fresh()->plan)->toBe('pro');

    $this->travelTo(now()->addWeeks(2));
    $this->workspace->syncPlanFromSubscription();

    expect($this->workspace->fresh()->plan)->toBe('free');
});

it('goes back to the free plan when the customer is deleted in Stripe', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    postJson('/stripe/webhook', ['id' => 'evt_1', 'type' => 'customer.deleted', 'data' => ['object' => ['id' => 'cus_test']]])->assertOk();

    $workspace = $this->workspace->fresh();

    expect($workspace->plan)->toBe('free')
        ->and($workspace->stripe_id)->toBeNull()
        ->and($workspace->currentSubscription())->toBeNull()
        ->and(Features::limit('projects', $workspace))->toBe(3);

    // Stripe reports the end of the subscription afterwards, for a customer nobody knows any more.
    postJson('/stripe/webhook', stripeSubscriptionEvent('deleted', (new Workspace)->forceFill(['stripe_id' => 'cus_test']), 'price_pro', 'canceled'))->assertOk();

    expect($workspace->fresh()->plan)->toBe('free');
});

it('leaves the plan alone when another customer is deleted, or on events that are not about subscriptions', function (array $event) {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    postJson('/stripe/webhook', $event)->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('pro')
        ->and($this->workspace->fresh()->stripe_id)->toBe('cus_test');
})->with([
    'another customer deleted' => [['id' => 'evt_1', 'type' => 'customer.deleted', 'data' => ['object' => ['id' => 'cus_other']]]],
    'an invoice paid' => [['id' => 'evt_2', 'type' => 'invoice.paid', 'data' => ['object' => ['id' => 'in_test', 'customer' => 'cus_test']]]],
    'a charge succeeded' => [['id' => 'evt_3', 'type' => 'charge.succeeded', 'data' => ['object' => ['id' => 'ch_test', 'customer' => 'cus_test']]]],
]);

it('keeps the current plan and logs a warning for a price it does not know', function () {
    Log::spy();

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();
    postJson('/stripe/webhook', stripeSubscriptionEvent('updated', $this->workspace, 'price_yearly_nobody_mapped'))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('pro');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context) => $context['price'] === 'price_yearly_nobody_mapped' && $context['workspace'] === $this->workspace->id)
        ->once();
});

it('handles the same event twice without a second subscription', function () {
    $event = stripeSubscriptionEvent('created', $this->workspace, 'price_pro');

    postJson('/stripe/webhook', $event)->assertOk();
    postJson('/stripe/webhook', $event)->assertOk();

    expect($this->workspace->subscriptions()->count())->toBe(1)
        ->and($this->workspace->fresh()->plan)->toBe('pro');
});

it('ignores an event for a customer it does not know', function () {
    $stranger = (new Workspace)->forceFill(['stripe_id' => 'cus_unknown']);

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $stranger, 'price_business'))->assertOk();

    expect(Workspace::query()->where('plan', '!=', 'free')->exists())->toBeFalse();
});

it('accepts a signed webhook and refuses an unsigned or badly signed one when a secret is set', function () {
    config()->set('cashier.webhook.secret', 'whsec_test');

    $event = stripeSubscriptionEvent('created', $this->workspace, 'price_business');

    postJson('/stripe/webhook', $event)->assertForbidden();
    postSignedStripeEvent($event, 'whsec_wrong')->assertForbidden();

    expect($this->workspace->fresh()->plan)->toBe('free');

    postSignedStripeEvent($event, 'whsec_test')->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('business');
});

it('refuses a customer.deleted webhook in production while the webhook secret is missing', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    app()->detectEnvironment(fn (): string => 'production');

    postJson('/stripe/webhook', ['id' => 'evt_1', 'type' => 'customer.deleted', 'data' => ['object' => ['id' => 'cus_test']]])->assertForbidden();

    expect($this->workspace->fresh()->plan)->toBe('pro')
        ->and($this->workspace->fresh()->stripe_id)->toBe('cus_test');
});

it('refuses webhooks in production while the webhook secret is missing', function () {
    Log::spy();
    app()->detectEnvironment(fn (): string => 'production');

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_business'))->assertForbidden();

    expect($this->workspace->fresh()->plan)->toBe('free')
        ->and($this->workspace->subscriptions()->count())->toBe(0);

    Log::shouldHaveReceived('error')->once();

    // With the secret in place, a signed event goes through.
    config()->set('cashier.webhook.secret', 'whsec_live');

    postSignedStripeEvent(stripeSubscriptionEvent('created', $this->workspace, 'price_business'), 'whsec_live')->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('business');
});

it('leaves other workspaces alone', function () {
    $other = workspace('free');

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    expect($other->fresh()->plan)->toBe('free');
});
