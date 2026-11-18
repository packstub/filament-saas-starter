<?php

use App\Billing\StripePrices;
use App\Filament\App\Pages\Billing;
use App\Filament\App\Widgets\WorkspaceOverview;
use App\Models\Project;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Exceptions;
use Packstub\Teams\Facades\Teams;
use Stripe\Exception\InvalidRequestException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->workspace = workspace('free');
    $this->workspace->forceFill(['stripe_id' => 'cus_test'])->save();
});

it('opens Billing for owners and admins only', function (string $role, int $status) {
    actingAs(memberOf($this->workspace, $role))
        ->get("/app/{$this->workspace->slug}/billing")
        ->assertStatus($status);
})->with([
    'owner' => ['owner', 200],
    'admin' => ['admin', 200],
    'member' => ['member', 403],
]);

it('keeps Billing from guests and from members of other workspaces', function () {
    get("/app/{$this->workspace->slug}/billing")->assertRedirect('/app/login');

    actingAs(Teams::ownersOf(workspace())->first())
        ->get("/app/{$this->workspace->slug}/billing")
        ->assertNotFound();
});

it('refuses every action to someone who lost their role while the page was open', function (string $action, array $arguments) {
    $stripe = fakeStripe();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', $action === 'completePayment' ? 'incomplete' : 'active'))->assertOk();

    $admin = memberOf($this->workspace, 'admin');

    inWorkspace($this->workspace->fresh(), $admin);

    $page = livewire(Billing::class)->assertOk();

    Teams::changeRole($this->workspace, $admin, 'member');

    // Asked for by the browser all the same, the page answers 403 and Stripe hears nothing.
    $page->assertActionHidden(TestAction::make($action)->arguments($arguments))
        ->call('mountAction', $action, $arguments)
        ->assertForbidden();

    expect($stripe->requests)->toBe([])
        ->and($this->workspace->subscriptions()->count())->toBe(1);
})->with([
    'subscribe' => ['subscribe', ['plan' => 'business']],
    'changePlan' => ['changePlan', ['plan' => 'business']],
    'manage' => ['manage', []],
    'completePayment' => ['completePayment', []],
    'resume' => ['resume', []],
]);

it('explains how to add Stripe keys when there are none, and shows the prices all the same', function () {
    config()->set('billing.plans.pro.monthly.price', null);

    actingAs(Teams::ownersOf($this->workspace)->first())
        ->get("/app/{$this->workspace->slug}/billing")
        ->assertOk()
        ->assertSee('Add your Stripe keys')
        ->assertSee('$29/mo')
        ->assertSee('STRIPE_SECRET')
        ->assertSee('Current plan')
        ->assertDontSee('Choose Pro');
});

it('offers the paid plans once Stripe is set up', function () {
    config()->set('cashier.key', 'pk_test_123');
    config()->set('cashier.secret', 'sk_test_123');
    config()->set('cashier.webhook.secret', 'whsec_123');

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->assertDontSee('Add your Stripe keys')
        ->assertSee('Choose Pro')
        ->assertSee('Choose Business')
        ->assertDontSee('Choose Free');
});

it('shows the monthly and yearly prices, and hides the switch when no plan is sold yearly', function () {
    config()->set('cashier.secret', 'sk_test_123');

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->assertSeeInOrder(['Monthly', 'Yearly'])
        ->assertSee(['$29/mo', '$99/mo', '$0'])
        ->assertDontSee('$290/yr')
        ->set('interval', 'yearly')
        ->assertSee(['$290/yr', '$990/yr', '$0'])
        ->assertDontSee('$29/mo')
        // Asked for an interval nothing is sold at, the page stays on monthly.
        ->set('interval', 'weekly')
        ->assertSee('$29/mo');

    config()->set('billing.plans.pro.yearly.price', null);
    config()->set('billing.plans.business.yearly.price', null);

    livewire(Billing::class)
        ->assertDontSee('Yearly')
        ->assertSee('$29/mo');
});

it('sends the customer to Stripe Checkout for the yearly price', function () {
    $stripe = fakeStripe([
        'GET /v1/customers/cus_test' => ['id' => 'cus_test', 'object' => 'customer'],
        'POST /v1/checkout/sessions' => ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test'],
    ]);

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->set('interval', 'yearly')
        ->assertSee('Choose Pro')
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'pro', 'interval' => 'yearly']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test');

    expect($stripe->params['POST /v1/checkout/sessions']['line_items'][0]['price'])->toBe('price_pro_yearly');
});

it('puts a yearly subscriber on the plan, and opens Billing on the yearly prices', function () {
    config()->set('cashier.secret', 'sk_test_123');

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro_yearly'))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('pro');

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSet('interval', 'yearly')
        ->assertSee('$290/yr')
        ->assertSee('Switch to Business')
        ->assertDontSee('Switch to yearly billing');
});

it('moves a monthly subscription to yearly billing on the same plan', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    $stripe = fakeStripe([
        'POST /v1/subscriptions/sub_test' => stripeSubscription('price_pro_yearly'),
        'GET /v1/subscriptions/sub_test' => stripeSubscription('price_pro_yearly'),
    ]);

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSet('interval', 'monthly')
        ->set('interval', 'yearly')
        ->assertSee('Switch to yearly billing')
        ->callAction(TestAction::make('changePlan')->arguments(['plan' => 'pro', 'interval' => 'yearly']))
        ->assertNotified("You're on the Pro plan");

    expect($stripe->params['POST /v1/subscriptions/sub_test']['items'][0]['price'])->toBe('price_pro_yearly')
        ->and($this->workspace->currentSubscription()->stripe_price)->toBe('price_pro_yearly')
        ->and($this->workspace->fresh()->plan)->toBe('pro');
});

it('starts a first subscription with the free trial', function () {
    $stripe = fakeStripe([
        'GET /v1/customers/cus_test' => ['id' => 'cus_test', 'object' => 'customer'],
        'POST /v1/checkout/sessions' => ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test'],
    ]);

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->assertSee('14-day free trial')
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'pro', 'interval' => 'monthly']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test');

    $trialEnd = $stripe->params['POST /v1/checkout/sessions']['subscription_data']['trial_end'];

    expect($trialEnd)->toBeGreaterThanOrEqual(now()->addDays(14)->getTimestamp() - 5)
        ->and($trialEnd)->toBeLessThanOrEqual(now()->addDays(14)->getTimestamp() + 5);
});

it('offers no second trial to a workspace that has had a subscription, nor any when trials are off', function (bool $hadSubscription, int $days) {
    config()->set('billing.trial_days', $days);

    if ($hadSubscription) {
        postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();
        postJson('/stripe/webhook', stripeSubscriptionEvent('deleted', $this->workspace, 'price_pro', 'canceled'))->assertOk();
    }

    $stripe = fakeStripe([
        'GET /v1/customers/cus_test' => ['id' => 'cus_test', 'object' => 'customer'],
        'POST /v1/checkout/sessions' => ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test'],
    ]);

    inWorkspace($this->workspace->fresh());

    expect($this->workspace->fresh()->trialDaysOnOffer())->toBe(0);

    livewire(Billing::class)
        ->assertDontSee('free trial')
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'pro', 'interval' => 'monthly']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test');

    expect($stripe->params['POST /v1/checkout/sessions']['subscription_data'])->not->toHaveKey('trial_end');
})->with([
    'had a subscription' => [true, 14],
    'trials off' => [false, 0],
]);

it('says when the free trial ends, on the Billing page and the dashboard', function () {
    config()->set('cashier.secret', 'sk_test_123');
    $trialEnd = now()->addDays(10);

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', 'trialing', ['trial_end' => $trialEnd->getTimestamp()]))->assertOk();

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSee('Your free trial ends on '.$trialEnd->toFormattedDateString())
        ->assertSee('Switch to Business')
        ->assertDontSee('day free trial');

    livewire(WorkspaceOverview::class)
        ->assertSee('Trial ends '.$trialEnd->format('M j'));
});

it('sends the customer to Stripe Checkout for the price of the plan', function () {
    $stripe = fakeStripe([
        'GET /v1/customers/cus_test' => ['id' => 'cus_test', 'object' => 'customer'],
        'POST /v1/checkout/sessions' => ['id' => 'cs_test', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/c/pay/cs_test'],
    ]);

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'pro']))
        ->assertRedirect('https://checkout.stripe.com/c/pay/cs_test');

    // The plan waits for the webhook that follows the payment.
    expect($stripe->requests)->toContain('POST /v1/checkout/sessions')
        ->and($this->workspace->fresh()->plan)->toBe('free');
});

it('thanks the customer who comes back from Stripe Checkout', function () {
    actingAs(Teams::ownersOf($this->workspace)->first())
        ->get("/app/{$this->workspace->slug}/billing?checkout=success")
        ->assertOk();

    Notification::assertNotified('Thank you!');
});

it('says so when Stripe cannot be reached, and reports the error', function () {
    Exceptions::fake();

    fakeStripe(['GET /v1/customers/cus_test' => ['error' => ['message' => 'Stripe is having a moment.']]]);

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'pro']))
        ->assertNoRedirect()
        ->assertNotified('Stripe could not complete that');

    Exceptions::assertReported(fn (InvalidRequestException $exception): bool => $exception->getMessage() === 'Stripe is having a moment.');
});

it('does nothing when asked to sell a plan that has no price', function () {
    $stripe = fakeStripe();

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'free']))
        ->assertNoRedirect();

    expect($stripe->requests)->toBe([])
        ->and($this->workspace->fresh()->plan)->toBe('free');
});

it('offers a switch instead of a checkout to a workspace that already pays', function () {
    config()->set('cashier.secret', 'sk_test_123');

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSee('Switch to Business')
        ->assertDontSee('Choose Business')
        ->assertActionVisible('manage');
});

it('moves the subscription to another plan', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    $stripe = fakeStripe([
        'POST /v1/subscriptions/sub_test' => stripeSubscription('price_business'),
        'GET /v1/subscriptions/sub_test' => stripeSubscription('price_business'),
    ]);

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->callAction(TestAction::make('changePlan')->arguments(['plan' => 'business']))
        ->assertNotified("You're on the Business plan");

    expect($stripe->requests)->toContain('POST /v1/subscriptions/sub_test')
        ->and($this->workspace->fresh()->plan)->toBe('business')
        ->and($this->workspace->currentSubscription()->stripe_price)->toBe('price_business');
});

it('sends the customer to the payment page when a switch needs the card confirmed', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    // Stripe answers the swap with a payment that needs 3D Secure.
    fakeStripe([
        'POST /v1/subscriptions/sub_test' => stripeSubscription('price_business', 'past_due'),
        'GET /v1/subscriptions/sub_test' => stripeSubscription('price_business', 'past_due', [
            'latest_invoice' => ['id' => 'in_test', 'object' => 'invoice', 'payments' => ['object' => 'list', 'data' => [
                ['id' => 'inpay_test', 'object' => 'invoice_payment', 'payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_needs_action']],
            ]]],
        ]),
        'GET /v1/payment_intents/pi_needs_action' => ['id' => 'pi_needs_action', 'object' => 'payment_intent', 'status' => 'requires_action'],
    ]);

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->callAction(TestAction::make('changePlan')->arguments(['plan' => 'business']))
        ->assertRedirect(route('cashier.payment', ['pi_needs_action', 'redirect' => Billing::getUrl()]));

    // The plan waits for the webhook that follows the payment.
    expect($this->workspace->fresh()->plan)->toBe('pro');
});

it('opens the customer portal of Stripe', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();

    fakeStripe(['POST /v1/billing_portal/sessions' => ['id' => 'bps_test', 'object' => 'billing_portal.session', 'url' => 'https://billing.stripe.com/p/session/test']]);

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->callAction('manage')
        ->assertRedirect('https://billing.stripe.com/p/session/test');
});

it('offers the portal, and neither a checkout nor a switch, while a payment is past due', function () {
    $stripe = fakeStripe();

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro'))->assertOk();
    postJson('/stripe/webhook', stripeSubscriptionEvent('updated', $this->workspace, 'price_pro', 'past_due'))->assertOk();

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSee('Your last payment did not go through')
        ->assertSee('Manage billing')
        ->assertDontSee('Choose ')
        ->assertDontSee('Switch to ')
        // Called directly, neither starts beside the subscription that is waiting for its payment.
        ->callAction(TestAction::make('subscribe')->arguments(['plan' => 'business']))
        ->assertNoRedirect()
        ->callAction(TestAction::make('changePlan')->arguments(['plan' => 'business']))
        ->assertNoRedirect();

    expect($stripe->requests)->toBe([])
        ->and($this->workspace->subscriptions()->count())->toBe(1)
        ->and($this->workspace->fresh()->plan)->toBe('pro');
});

it('offers the portal and Resume, and no switch, for a subscription Stripe paused after its trial', function () {
    $stripe = fakeStripe();

    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', 'trialing', ['trial_end' => now()->addDay()->getTimestamp()]))->assertOk();
    postJson('/stripe/webhook', stripeSubscriptionEvent('updated', $this->workspace, 'price_pro', 'paused', ['trial_end' => now()->subDay()->getTimestamp()]))->assertOk();

    expect($this->workspace->fresh()->plan)->toBe('free');

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSee('Your trial ended without a payment method')
        ->assertSee('Resume subscription')
        ->assertDontSee('Choose ')
        ->assertDontSee('Switch to ')
        ->callAction(TestAction::make('changePlan')->arguments(['plan' => 'business', 'interval' => 'monthly']))
        ->assertNoRedirect();

    expect($stripe->requests)->toBe([]);
});

it('resumes a paused subscription once Stripe has a card, and says so when it has none', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', 'paused'))->assertOk();

    fakeStripe(['POST /v1/subscriptions/sub_test/resume' => ['error' => ['message' => 'This customer has no attached payment source.']]]);

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->callAction('resume')
        ->assertNotified('Stripe could not complete that');

    expect($this->workspace->fresh()->plan)->toBe('free');

    $stripe = fakeStripe(['POST /v1/subscriptions/sub_test/resume' => stripeSubscription('price_pro')]);

    livewire(Billing::class)
        ->callAction('resume')
        ->assertNotified("You're on the Pro plan");

    expect($stripe->params['POST /v1/subscriptions/sub_test/resume']['billing_cycle_anchor'])->toBe('now')
        ->and($this->workspace->fresh()->plan)->toBe('pro')
        ->and($this->workspace->currentSubscription()->stripe_status)->toBe('active');
});

it('sends an unfinished first payment to the payment page, and offers no second checkout', function () {
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $this->workspace, 'price_pro', 'incomplete'))->assertOk();

    fakeStripe([
        'GET /v1/subscriptions/sub_test' => stripeSubscription('price_pro', 'incomplete', [
            'latest_invoice' => ['id' => 'in_test', 'object' => 'invoice', 'payments' => ['object' => 'list', 'data' => [
                ['id' => 'inpay_test', 'object' => 'invoice_payment', 'payment' => ['type' => 'payment_intent', 'payment_intent' => 'pi_first']],
            ]]],
        ]),
        'GET /v1/payment_intents/pi_first' => ['id' => 'pi_first', 'object' => 'payment_intent', 'status' => 'requires_action'],
    ]);

    inWorkspace($this->workspace->fresh());

    livewire(Billing::class)
        ->assertSee('Your payment is not finished yet')
        ->assertDontSee('Choose ')
        ->callAction('completePayment')
        ->assertRedirect(route('cashier.payment', ['pi_first', 'redirect' => Billing::getUrl()]));
});

it('sells nothing in production until the webhook secret is set', function () {
    config()->set('cashier.secret', 'sk_live_123');
    app()->detectEnvironment(fn (): string => 'production');

    inWorkspace($this->workspace);

    livewire(Billing::class)
        ->assertSee('Paid plans are on their way')
        ->assertDontSee('STRIPE_WEBHOOK_SECRET')
        ->assertDontSee('Choose Pro');
});

it('knows when plans can be sold', function (?string $secret, ?string $webhookSecret, string $environment, bool $configured) {
    config()->set('cashier.secret', $secret);
    config()->set('cashier.webhook.secret', $webhookSecret);
    app()->detectEnvironment(fn (): string => $environment);

    expect(StripePrices::configured())->toBe($configured);
})->with([
    'no keys' => [null, null, 'local', false],
    'an empty secret' => ['', 'whsec_123', 'local', false],
    'a secret, locally' => ['sk_test_123', null, 'local', true],
    'a secret without the webhook secret, in production' => ['sk_live_123', null, 'production', false],
    'both secrets, in production' => ['sk_live_123', 'whsec_123', 'production', true],
]);

it('maps Stripe prices to plans and back', function (?string $price, ?string $plan) {
    expect(StripePrices::plan($price))->toBe($plan);
})->with([
    'the Pro price' => ['price_pro', 'pro'],
    'the Business price' => ['price_business', 'business'],
    'the yearly Pro price' => ['price_pro_yearly', 'pro'],
    'the yearly Business price' => ['price_business_yearly', 'business'],
    'a price no plan has' => ['price_yearly', null],
    'an empty price' => ['', null],
    'no price' => [null, null],
]);

it('knows the interval each price bills at', function (?string $price, ?string $interval) {
    expect(StripePrices::interval($price))->toBe($interval);
})->with([
    'monthly' => ['price_pro', 'monthly'],
    'yearly' => ['price_business_yearly', 'yearly'],
    'a price no plan has' => ['price_weekly', null],
    'no price' => [null, null],
]);

it('sells a plan monthly only when it has no yearly price', function () {
    config()->set('billing.plans.pro.yearly.price', null);

    expect(StripePrices::for('pro', 'yearly'))->toBeNull()
        // The amount is text for people, shown whether or not Stripe has the price yet.
        ->and(StripePrices::amount('pro', 'yearly'))->toBe('$290/yr')
        ->and(StripePrices::for('business', 'yearly'))->toBe('price_business_yearly')
        ->and(StripePrices::intervals())->toBe(['monthly', 'yearly']);

    config()->set('billing.plans.business.yearly.price', '');

    expect(StripePrices::intervals())->toBe(['monthly']);
});

it('sells only the plans that have a price', function () {
    config()->set('billing.plans.business.monthly.price', '');

    expect(StripePrices::for('pro'))->toBe('price_pro')
        ->and(StripePrices::for('business'))->toBeNull()
        ->and(StripePrices::for('free'))->toBeNull()
        // An empty price in the config never matches a subscription without one.
        ->and(StripePrices::plan(''))->toBeNull();
});

it('shows on the dashboard what the workspace has used of its plan', function () {
    Project::factory()->count(2)->for($this->workspace)->create();
    Teams::invite($this->workspace, 'nina@example.com', send: false);

    inWorkspace($this->workspace);

    // The owner and the pending invitation each take a seat.
    livewire(WorkspaceOverview::class)
        ->assertSeeInOrder(['Plan', 'Free', 'Projects', '2 of 3', 'Seats', '2 of 2']);

    $this->workspace->changePlan('business');

    livewire(WorkspaceOverview::class)
        ->assertSee('Business')
        ->assertDontSee(' of ');
});

it('still reads the price keys of a copy made before yearly prices', function () {
    $config = fn (array $env): array => (function () use ($env) {
        foreach ($env as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        try {
            return require config_path('billing.php');
        } finally {
            foreach (array_keys($env) as $key) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);
            }
        }
    })();

    // phpunit.xml sets the new keys; without them, the old ones count.
    $before = ['STRIPE_PRICE_PRO_MONTHLY' => $_ENV['STRIPE_PRICE_PRO_MONTHLY'] ?? null, 'STRIPE_PRICE_BUSINESS_MONTHLY' => $_ENV['STRIPE_PRICE_BUSINESS_MONTHLY'] ?? null];
    foreach (array_keys($before) as $key) {
        putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }

    try {
        $billing = $config(['STRIPE_PRICE_PRO' => 'price_old_pro', 'STRIPE_PRICE_BUSINESS' => 'price_old_business']);

        expect($billing['plans']['pro']['monthly']['price'])->toBe('price_old_pro')
            ->and($billing['plans']['business']['monthly']['price'])->toBe('price_old_business');

        // The new name wins when both are set.
        $billing = $config(['STRIPE_PRICE_PRO' => 'price_old_pro', 'STRIPE_PRICE_PRO_MONTHLY' => 'price_new_pro']);

        expect($billing['plans']['pro']['monthly']['price'])->toBe('price_new_pro');
    } finally {
        foreach ($before as $key => $value) {
            if ($value !== null) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $_SERVER[$key] = $value;
            }
        }
    }
});
