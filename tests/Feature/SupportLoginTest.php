<?php

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\Admin\Resources\Workspaces\Pages\ManageWorkspaces;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\AccountSwitcher\Models\AccountSwitch;
use Packstub\Features\Facades\Features;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\seed;
use function Pest\Livewire\livewire;

it('opens the admin panel for admins only', function () {
    actingAs(User::factory()->create())->get('/admin')->assertForbidden();
    actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();

    actingAs(admin())->get('/admin/users')->assertOk();
});

it('lets an admin sign in as a customer and switch back', function () {
    $admin = admin();
    $workspace = workspace();
    $customer = Teams::ownersOf($workspace)->first();

    inAdminPanel($admin);

    livewire(ManageUsers::class)
        ->callAction(TestAction::make('impersonate')->table($customer))
        ->assertRedirect('/app');

    assertAuthenticatedAs($customer);

    expect(AccountSwitcher::isImpersonating())->toBeTrue()
        ->and(AccountSwitch::query()->count())->toBe(1);

    // "Switch back" in the banner returns the admin to the page they came from.
    post(route('filament.app.account-switcher.switch-back'))->assertRedirect();

    assertAuthenticatedAs($admin);

    expect(AccountSwitcher::isImpersonating())->toBeFalse();
});

it('never lets a customer sign in as someone else, or anyone as an admin', function () {
    $admin = admin();
    $otherAdmin = admin();
    $customer = User::factory()->create();

    expect(AccountSwitcher::canImpersonate($customer, User::factory()->create()))->toBeFalse()
        ->and(AccountSwitcher::canImpersonate($admin, $otherAdmin))->toBeFalse()
        ->and(AccountSwitcher::canImpersonate($admin, $customer))->toBeTrue();

    inAdminPanel($admin);

    livewire(ManageUsers::class)
        ->assertActionVisible(TestAction::make('impersonate')->table($customer))
        ->assertActionHidden(TestAction::make('impersonate')->table($otherAdmin));
});

it('lets an admin put a workspace on a plan by hand', function () {
    $workspace = workspace('free');

    inAdminPanel();

    livewire(ManageWorkspaces::class)
        ->callAction(TestAction::make('changePlan')->table($workspace), ['plan' => 'business'])
        ->assertHasNoActionErrors();

    expect($workspace->fresh()->plan)->toBe('business')
        ->and(Features::limit('projects', $workspace->fresh()))->toBeNull();
});

it('shows the demo accounts as one-click logins in the local environment only', function (string $environment, bool $shown) {
    seed();

    app()->detectEnvironment(fn (): string => $environment);

    $page = get('/app/login')->assertOk();

    $shown ? $page->assertSee(DatabaseSeeder::OWNER_EMAIL) : $page->assertDontSee(DatabaseSeeder::OWNER_EMAIL);
})->with([
    'local' => ['local', true],
    'staging' => ['staging', false],
    'production' => ['production', false],
]);

it('sends a guest to the sign-in page of the admin panel', function (string $page) {
    get($page)->assertRedirect('/admin/login');
})->with(['/admin', '/admin/users', '/admin/workspaces', '/admin/feature-flags', '/admin/feature-overrides']);

it('keeps every page of the admin panel from customers', function (string $page) {
    $workspace = workspace();

    actingAs(Teams::ownersOf($workspace)->first())->get($page)->assertForbidden();
})->with(['/admin', '/admin/users', '/admin/workspaces', '/admin/feature-flags', '/admin/feature-overrides']);

it('opens every page of the admin panel to an admin', function (string $page) {
    workspace('pro');

    actingAs(admin())->get($page)->assertOk();
})->with(['/admin/users', '/admin/workspaces', '/admin/feature-flags', '/admin/feature-overrides']);

it('keeps the admin panel closed while an admin is signed in as a customer', function () {
    $customer = Teams::ownersOf(workspace())->first();

    inAdminPanel();

    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($customer));

    // The first page after the switch falls back to a panel the customer may open; after that it is a plain 403.
    get('/admin/users')->assertRedirect();
    get('/admin/users')->assertForbidden();
    get('/admin/workspaces')->assertForbidden();
});

it('refuses a plan that does not exist', function () {
    $workspace = workspace('pro');

    inAdminPanel();

    livewire(ManageWorkspaces::class)
        ->callAction(TestAction::make('changePlan')->table($workspace), ['plan' => 'enterprise'])
        ->assertHasActionErrors(['plan']);

    expect($workspace->fresh()->plan)->toBe('pro');
});

it('lists the workspaces with their plan, subscription and members', function () {
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    memberOf($workspace, 'member');
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro', 'past_due'))->assertOk();

    inAdminPanel();

    livewire(ManageWorkspaces::class)
        ->assertCanSeeTableRecords([$workspace])
        ->assertTableColumnStateSet('plan', 'pro', $workspace)
        ->assertTableColumnStateSet('subscription', 'past_due', $workspace)
        ->assertTableColumnStateSet('members_count', 2, $workspace);
});

it('lists the users with the number of workspaces they are in', function () {
    $user = Teams::ownersOf(workspace())->first();
    Teams::addMember(workspace(), $user, 'member');

    inAdminPanel();

    livewire(ManageUsers::class)
        ->assertTableColumnStateSet('joined_teams_count', 2, $user)
        ->assertTableColumnStateSet('joined_teams_count', 0, User::factory()->create());
});

it('asks the database the same number of questions however many rows the admin tables list', function (string $page) {
    $admin = inAdminPanel();

    $render = fn (): int => countQueries(fn () => livewire($page)->assertOk());

    workspace('pro');
    $few = $render();

    foreach (range(1, 6) as $i) {
        $workspace = workspace('pro');
        $workspace->forceFill(['stripe_id' => "cus_{$i}"])->save();
        memberOf($workspace, 'member');
    }

    expect($render())->toBe($few);
})->with([
    'workspaces' => [ManageWorkspaces::class],
    'users' => [ManageUsers::class],
]);
