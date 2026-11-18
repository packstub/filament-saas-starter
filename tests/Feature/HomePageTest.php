<?php

use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => $this->withoutVite());

it('shows the home page with the way in', function () {
    get('/')
        ->assertOk()
        ->assertSee('/app/register')
        ->assertSee('/app/login')
        ->assertSee(config('site.tagline'));
});

it('offers the app instead of signing in to someone who is signed in', function () {
    actingAs(User::factory()->create());

    get('/')->assertOk()->assertSee('Open the app')->assertDontSee('/app/login');
});

it('prices every plan from the billing config, monthly and yearly', function () {
    get('/')
        ->assertOk()
        ->assertSeeInOrder(['Free', '$0', 'Pro', '$29/mo', '$290/yr', '50 projects', 'Business', '$99/mo', '$990/yr', 'Unlimited projects', 'Priority support'])
        ->assertSee('Monthly')
        ->assertSee('Yearly');
});

it('shows only the intervals a plan is sold at once Stripe prices are set', function () {
    config()->set('billing.plans.pro.yearly.price', null);
    config()->set('billing.plans.business.yearly.price', null);

    get('/')->assertOk()->assertSee('$29/mo')->assertDontSee('$290/yr')->assertDontSee('Yearly');
});

it('shows every amount before any Stripe price is set, so a new project looks complete', function () {
    foreach (['pro', 'business'] as $plan) {
        config()->set("billing.plans.{$plan}.monthly.price", null);
        config()->set("billing.plans.{$plan}.yearly.price", null);
    }

    get('/')->assertOk()->assertSee('$29/mo')->assertSee('$990/yr');
});

it('mentions the free trial only while there is one', function (int $days, bool $shown) {
    config()->set('billing.trial_days', $days);

    $response = get('/')->assertOk();

    $shown
        ? $response->assertSee("{$days}-day free trial")->assertSee('Start your free trial')
        : $response->assertDontSee('free trial');
})->with([
    'a 14-day trial' => [14, true],
    'trials off' => [0, false],
]);

it('has what search engines and link previews read', function () {
    get('/')
        ->assertOk()
        ->assertSee('<meta name="description" content="'.e(config('site.description')).'">', escape: false)
        ->assertSee('<link rel="canonical" href="'.url('/').'">', escape: false)
        ->assertSee('property="og:title"', escape: false)
        ->assertSee('name="twitter:card"', escape: false);
});

it('lists the public pages in the sitemap', function () {
    get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<loc>'.route('terms').'</loc>', escape: false)
        ->assertSee('<loc>'.route('privacy').'</loc>', escape: false);
});

it('keeps search engines out of the panels, and out of everything before production', function () {
    get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", escape: false);

    app()->detectEnvironment(fn () => 'production');

    get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /admin')
        ->assertSee('Disallow: /app/')
        ->assertSee('Allow: /app/register')
        ->assertSee('Sitemap: '.route('sitemap'));
});

it('has Terms and Privacy pages marked as placeholders', function (string $path, string $title) {
    get($path)
        ->assertOk()
        ->assertSee($title)
        ->assertSee('Replace with your own.')
        ->assertSee('<meta name="robots" content="noindex">', escape: false);
})->with([
    ['/terms', 'Terms of Service'],
    ['/privacy', 'Privacy Policy'],
]);
