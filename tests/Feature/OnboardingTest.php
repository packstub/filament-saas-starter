<?php

use App\Filament\App\Widgets\GettingStarted;
use App\Models\Project;
use App\Models\User;
use Packstub\Teams\Facades\Teams;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

/**
 * Which steps the checklist shows as done, keyed by step.
 *
 * @return array<string, bool>
 */
function stepsDone($workspace, User $user): array
{
    return collect(GettingStarted::steps($workspace, $user))->pluck('done', 'key')->all();
}

it('starts a new workspace with nothing done', function () {
    $workspace = workspace();
    $owner = inWorkspace($workspace);

    expect(stepsDone($workspace, $owner))->toBe(['invite' => false, 'project' => false, 'plan' => false, 'two-factor' => false]);

    livewire(GettingStarted::class)
        ->assertSee('Get started')
        ->assertSee('0 of 4 done')
        ->assertSee('Invite a teammate');
});

it('ticks each step off from what the workspace really has', function () {
    $owner = User::factory()->withAppAuthentication()->create();
    $workspace = workspace(owner: $owner);

    Teams::invite($workspace, 'nina@example.com', send: false);
    Project::factory()->for($workspace)->create();
    $workspace->changePlan('pro');

    expect(stepsDone($workspace->fresh(), $owner))->toBe(['invite' => true, 'project' => true, 'plan' => true, 'two-factor' => true]);
});

it('counts a teammate who joined as invited', function () {
    $workspace = workspace();
    memberOf($workspace, 'member');

    expect(stepsDone($workspace, Teams::ownersOf($workspace)->first())['invite'])->toBeTrue();
});

it('counts a subscription as a chosen plan, even on its trial', function () {
    $workspace = workspace();
    $workspace->subscriptions()->create(['type' => 'default', 'stripe_id' => 'sub_1', 'stripe_status' => 'trialing', 'stripe_price' => 'price_pro']);

    expect(stepsDone($workspace, Teams::ownersOf($workspace)->first())['plan'])->toBeTrue();
});

it('goes away once every step is done', function () {
    $owner = User::factory()->withAppAuthentication()->create();
    $workspace = workspace('pro', $owner);
    memberOf($workspace, 'member');
    Project::factory()->for($workspace)->create();
    inWorkspace($workspace, $owner);

    expect(GettingStarted::canView())->toBeFalse();
});

it('is for owners and admins, who can act on it', function (string $role, bool $shown) {
    $workspace = workspace();
    inWorkspace($workspace, memberOf($workspace, $role));

    expect(GettingStarted::canView())->toBe($shown);
})->with([
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'member' => ['member', false],
]);

it('can be dismissed for everyone in the workspace', function () {
    $workspace = workspace();
    $admin = memberOf($workspace, 'admin');
    inWorkspace($workspace);

    livewire(GettingStarted::class)->call('dismiss');

    expect($workspace->fresh()->onboarding_dismissed_at)->not->toBeNull();

    inWorkspace($workspace->fresh(), $admin);

    expect(GettingStarted::canView())->toBeFalse();
});

it('is dismissed per workspace, never for another', function () {
    $owner = User::factory()->create();
    $first = workspace(owner: $owner);
    $second = workspace(owner: $owner);
    inWorkspace($first, $owner);

    livewire(GettingStarted::class)->call('dismiss');

    inWorkspace($second, $owner);

    expect($second->fresh()->onboarding_dismissed_at)->toBeNull()
        ->and(GettingStarted::canView())->toBeTrue();
});

it('shows on the dashboard of a new workspace', function () {
    $workspace = workspace();
    inWorkspace($workspace);

    get("/app/{$workspace->slug}")->assertOk()->assertSeeLivewire(GettingStarted::class);
});

it('refuses a dismissal from a member in dismiss() itself', function () {
    $workspace = workspace();
    inWorkspace($workspace, memberOf($workspace, 'member'));

    // Called directly, without Livewire, whose hydration would already refuse the widget
    // to a member (canView()): this checks dismiss()'s own guard.
    expect(fn () => (new GettingStarted)->dismiss())->toThrow(HttpException::class)
        ->and($workspace->fresh()->onboarding_dismissed_at)->toBeNull();
});
