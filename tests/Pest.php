<?php

use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Packstub\Teams\Facades\Teams;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

use function Pest\Laravel\actingAs;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->afterEach(fn () => ApiRequestor::setHttpClient(null))
    ->in('Feature');

/**
 * A workspace on a plan, owned by the given user (or a new one).
 */
function workspace(string $plan = 'free', ?User $owner = null): Workspace
{
    $workspace = Workspace::factory()->onPlan($plan)->create();

    Teams::addMember($workspace, $owner ?? User::factory()->create(), Teams::ownerRole());

    return $workspace;
}

/**
 * A new member of the workspace with the given role: `owner`, `admin` or `member`.
 */
function memberOf(Workspace $workspace, string $role): User
{
    $user = User::factory()->create();

    Teams::addMember($workspace, $user, $role);

    return $user;
}

/**
 * Someone who may open the admin panel: `is_admin`, with two-factor authentication set up.
 */
function admin(): User
{
    return User::factory()->admin()->withAppAuthentication()->create();
}

/**
 * Sign in and open the app panel in a workspace, as a Livewire test needs it.
 */
function inWorkspace(Workspace $workspace, ?User $user = null): User
{
    $user ??= Teams::ownersOf($workspace)->first();

    actingAs($user);
    Filament::setCurrentPanel('app');
    Filament::setTenant($workspace, isQuiet: true);
    // Registers the tenant scopes and the listener that puts new records in the workspace.
    Filament::bootCurrentPanel();

    return $user;
}

/**
 * Sign in and open the admin panel, as a Livewire test needs it.
 */
function inAdminPanel(?User $user = null): User
{
    $user ??= admin();

    actingAs($user);
    Filament::setCurrentPanel('admin');

    return $user;
}

/**
 * A Stripe subscription webhook as Cashier receives it.
 */
function stripeSubscriptionEvent(string $type, Workspace $workspace, string $price, string $status = 'active', array $extra = []): array
{
    return [
        'id' => 'evt_'.fake()->uuid(),
        'type' => "customer.subscription.{$type}",
        'data' => [
            'object' => [
                'id' => 'sub_test',
                'customer' => $workspace->stripe_id,
                'status' => $status,
                'cancel_at_period_end' => false,
                'metadata' => ['type' => 'default'],
                'items' => [
                    'data' => [[
                        'id' => 'si_test',
                        'quantity' => $extra['quantity'] ?? 1,
                        'price' => ['id' => $price, 'product' => 'prod_test'],
                    ]],
                ],
                ...$extra,
            ],
        ],
    ];
}

/**
 * Stripe's answer to a subscription being read or changed.
 */
function stripeSubscription(string $price, string $status = 'active', array $extra = []): array
{
    return [
        'id' => 'sub_test',
        'object' => 'subscription',
        'status' => $status,
        'items' => ['object' => 'list', 'data' => [[
            'id' => 'si_test',
            'object' => 'subscription_item',
            'quantity' => $extra['quantity'] ?? 1,
            'price' => ['id' => $price, 'object' => 'price', 'product' => 'prod_test'],
        ]]],
        ...$extra,
    ];
}

/**
 * Post an event the way Stripe does: the raw JSON, signed with the webhook secret.
 */
function postSignedStripeEvent(array $event, string $secret): TestResponse
{
    $payload = json_encode($event);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return test()->call('POST', '/stripe/webhook', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ], $payload);
}

/**
 * Stripe's API, answered by the test instead of Stripe. Give the answer to
 * every call the app is expected to make, keyed like `POST /v1/checkout/sessions`;
 * an answer with an `error` key is sent as a failed request. A call without
 * an answer fails the test. The calls made are in `->requests`, the
 * parameters of the last call of each kind in `->params`.
 *
 * @param  array<string, array<string, mixed>>  $responses
 */
function fakeStripe(array $responses = []): object
{
    config()->set('cashier.secret', 'sk_test_123');

    $stripe = new class($responses) implements ClientInterface
    {
        /** @var list<string> */
        public array $requests = [];

        /** @var array<string, array<string, mixed>> */
        public array $params = [];

        public function __construct(private array $responses) {}

        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
        {
            $this->requests[] = $request = strtoupper($method).' '.parse_url($absUrl, PHP_URL_PATH);
            $this->params[$request] = $params;

            $response = $this->responses[$request] ?? throw new LogicException("The test gave no answer for the Stripe call [{$request}].");

            return [json_encode($response), isset($response['error']) ? 400 : 200, []];
        }
    };

    ApiRequestor::setHttpClient($stripe);

    return $stripe;
}

/**
 * The number of database queries the callback runs.
 */
function countQueries(Closure $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    DB::disableQueryLog();

    return count(DB::getQueryLog());
}
