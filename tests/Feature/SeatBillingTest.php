<?php

use App\Filament\App\Pages\Billing;
use App\Listeners\SyncSeatQuantityWithStripe;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Queue;
use Packstub\Teams\Facades\Teams;
use Stripe\Exception\InvalidRequestException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

beforeEach(function () {
    config()->set('billing.plans.pro.per_seat', true);

    // On Pro already (its 10 seats), as a workspace the admin panel put on it.
    $this->workspace = workspace('pro');
    $this->workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    $this->owner = Teams::ownersOf($this->workspace)->first();
});

/**
 * A running Pro subscription billed for the given number of seats.
 */
function subscribeToPro(int $seats = 1): void
{
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', test()->workspace, 'price_pro', 'active', ['quantity' => $seats]))->assertOk();
}

it('bills a flat plan once, however many members there are', function () {
    config()->set('billing.plans.pro.per_seat', false);
    subscribeToPro();

    $stripe = fakeStripe();

    memberOf($this->workspace, 'member');
    memberOf($this->workspace, 'admin');

    expect($stripe->requests)->toBe([])
        ->and($this->workspace->currentSubscription()->quantity)->toBe(1);
});

it('opens Checkout for a per-seat plan with a seat for every member', function () {
    memberOf($this->workspace, 'member');
    memberOf($this->workspace, 'admin');
    // A pending invitation holds a seat against the limit, but is not billed until accepted.
    Teams::invite($this->workspace, 'nina@example.com', send: false);

    $stripe = fakeStripe([
        'GET /v1/customers/cus_test' => ['id' => 'cus_test', 'object' => 'customer'],
        'POST /v1/checkout/sessions' => ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test'],
    ]);

    inWorkspace($this->workspace, $this->owner);

    livewire(Billing::class)
        ->assertSee('Per member: 3 members today')
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'pro', 'interval' => 'monthly']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test');

    expect($stripe->params['POST /v1/checkout/sessions']['line_items'][0])->toMatchArray(['price' => 'price_pro', 'quantity' => 3]);
});

it('adds a seat to the subscription when someone joins, and takes it off when they leave', function () {
    subscribeToPro(1);

    $stripe = fakeStripe(['POST /v1/subscriptions/sub_test' => stripeSubscription('price_pro', extra: ['quantity' => 2])]);

    $member = memberOf($this->workspace, 'member');

    expect($stripe->params['POST /v1/subscriptions/sub_test']['quantity'])->toBe(2)
        ->and($this->workspace->currentSubscription()->quantity)->toBe(2);

    $stripe = fakeStripe(['POST /v1/subscriptions/sub_test' => stripeSubscription('price_pro', extra: ['quantity' => 1])]);

    Teams::removeMember($this->workspace, $member);

    expect($stripe->params['POST /v1/subscriptions/sub_test']['quantity'])->toBe(1)
        ->and($this->workspace->currentSubscription()->quantity)->toBe(1);
});

it('adds a seat when an invitation is accepted, not when it is sent', function () {
    subscribeToPro(1);

    $stripe = fakeStripe();
    $invitation = Teams::invite($this->workspace, 'nina@example.com', send: false);

    expect($stripe->requests)->toBe([]);

    $stripe = fakeStripe(['POST /v1/subscriptions/sub_test' => stripeSubscription('price_pro', extra: ['quantity' => 2])]);

    $nina = User::factory()->create(['email' => 'nina@example.com']);
    actingAs($nina);
    get(Teams::acceptUrl($invitation));

    expect(Teams::isMember($this->workspace, $nina))->toBeTrue()
        ->and($stripe->params['POST /v1/subscriptions/sub_test']['quantity'])->toBe(2);
});

it('leaves a subscription that is not in good standing alone', function (string $status) {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', $status))->assertOk();

    $stripe = fakeStripe();

    memberOf($this->workspace, 'member');

    expect($stripe->requests)->toBe([]);
})->with(['incomplete', 'past_due', 'unpaid']);

it('runs on the queue once the membership is saved', function () {
    Queue::fake();
    subscribeToPro(1);

    memberOf($this->workspace, 'member');

    Queue::assertPushed(CallQueuedListener::class, fn ($job): bool => $job->class === SyncSeatQuantityWithStripe::class);
});

it('keeps the new member when Stripe cannot be reached, and reports it', function () {
    Exceptions::fake();
    subscribeToPro(1);

    fakeStripe(['POST /v1/subscriptions/sub_test' => ['error' => ['message' => 'Stripe is having a moment.']]]);

    $member = memberOf($this->workspace, 'member');

    expect(Teams::isMember($this->workspace, $member))->toBeTrue();

    Exceptions::assertReported(fn (InvalidRequestException $exception): bool => $exception->getMessage() === 'Stripe is having a moment.');
});

it('puts a drifted quantity right when the next subscription webhook arrives', function () {
    memberOf($this->workspace, 'member');
    memberOf($this->workspace, 'member');

    $stripe = fakeStripe(['POST /v1/subscriptions/sub_test' => stripeSubscription('price_pro', extra: ['quantity' => 3])]);

    // Stripe says one seat; the workspace has three members.
    subscribeToPro(1);

    expect($stripe->params['POST /v1/subscriptions/sub_test']['quantity'])->toBe(3)
        ->and($this->workspace->currentSubscription()->quantity)->toBe(3);
});

it('moves between a flat and a per-seat plan with the right quantity', function () {
    config()->set('billing.plans.business.per_seat', false);
    memberOf($this->workspace, 'member');
    subscribeToPro(2);

    $stripe = fakeStripe([
        'POST /v1/subscriptions/sub_test' => stripeSubscription('price_business'),
        'GET /v1/subscriptions/sub_test' => stripeSubscription('price_business'),
    ]);

    inWorkspace($this->workspace, $this->owner);

    livewire(Billing::class)
        ->callAction(TestAction::make('changePlan')->arguments(['plan' => 'business', 'interval' => 'monthly']))
        ->assertNotified("You're on the Business plan");

    expect($stripe->params['POST /v1/subscriptions/sub_test']['items'][0])->toMatchArray(['price' => 'price_business', 'quantity' => 1]);
});
