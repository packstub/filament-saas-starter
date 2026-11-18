<?php

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\App\Pages\Tenancy\EditWorkspace;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Laravel\Cashier\Subscription;
use Laravel\Cashier\SubscriptionItem;
use Packstub\Features\Facades\Features;
use Packstub\Features\Models\FeatureOverride;
use Packstub\Teams\Facades\Teams;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\InvalidRequestException;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

it('never gives a workspace a slug that a page of the panel already has', function (string $name) {
    $workspace = Workspace::named($name);

    expect($workspace->name)->toBe($name)
        ->and($workspace->slug)->toStartWith(Str::slug($name).'-')
        ->and(Workspace::reservedSlugs())->toContain(Str::slug($name));

    // Its owner lands in the workspace, not on the page of the same name.
    $owner = User::factory()->create();
    Teams::addMember($workspace, $owner, Teams::ownerRole());

    actingAs($owner)->get('/app')->assertRedirect("/app/{$workspace->slug}");
    get("/app/{$workspace->slug}")->assertOk()->assertSee($name);
})->with([
    'New',
    'Login',
    'Logout',
    'Register',
    'Profile',
    'Invitations',
    'Password reset',
    'Email verification',
    'Email change verification',
    'Account switcher',
]);

it('reads the reserved slugs from the routes of the panel', function () {
    $reserved = Workspace::reservedSlugs();

    expect($reserved)->not->toBeEmpty()
        ->and($reserved)->not->toContain('projects', 'billing', '{tenant}');

    expect(array_map(Workspace::uniqueSlug(...), $reserved))->not->toContain(...$reserved);
});

it('reserves the first segment of every route when the panel sits at the root of the site', function () {
    Filament::getPanel('app')->path('');

    expect(Workspace::reservedSlugs())->toContain('app', 'admin', 'stripe', 'up')
        ->and(Workspace::reservedSlugs())->not->toContain('')
        ->and(Workspace::uniqueSlug('Admin'))->toStartWith('admin-');
});

it('falls back to a plain slug for a name without letters or digits', function () {
    expect(Workspace::uniqueSlug('日本語'))->toBe('workspace')
        ->and(Workspace::named('!!!')->slug)->toBe('workspace')
        ->and(Workspace::named('???')->slug)->toStartWith('workspace-');
});

it('opens the workspace settings for owners and admins only', function (string $role, int $status) {
    $workspace = workspace();

    actingAs(memberOf($workspace, $role))
        ->get("/app/{$workspace->slug}/profile")
        ->assertStatus($status);
})->with([
    'owner' => ['owner', 200],
    'admin' => ['admin', 200],
    'member' => ['member', 404],
]);

it('keeps the workspace settings from guests and from members of other workspaces', function () {
    $workspace = workspace();

    get("/app/{$workspace->slug}/profile")->assertRedirect('/app/login');

    actingAs(Teams::ownersOf(workspace())->first())
        ->get("/app/{$workspace->slug}/profile")
        ->assertNotFound();
});

it('renames a workspace and keeps its slug', function (string $role) {
    $workspace = workspace();
    $slug = $workspace->slug;

    inWorkspace($workspace, memberOf($workspace, $role));

    livewire(EditWorkspace::class)
        ->fillForm(['name' => 'Acme International'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($workspace->fresh()->name)->toBe('Acme International')
        ->and($workspace->fresh()->slug)->toBe($slug);

    livewire(EditWorkspace::class)
        ->fillForm(['name' => str_repeat('x', Workspace::NAME_MAX_LENGTH + 1)])
        ->call('save')
        ->assertHasFormErrors(['name']);
})->with(['owner', 'admin']);

it('refuses a rename from a member', function () {
    $workspace = workspace();

    inWorkspace($workspace, memberOf($workspace, 'member'));

    livewire(EditWorkspace::class)->assertNotFound();
});

it('removes everything a deleted workspace had, and nothing of another', function () {
    $workspace = workspace('pro');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    Project::factory()->count(2)->for($workspace)->create();
    Features::override($workspace, 'projects', 100);
    Teams::invite($workspace, 'nina@example.com', send: false);
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();

    $other = workspace('pro');
    $theirs = Project::factory()->for($other)->create();
    Features::override($other, 'projects', 100);

    expect(Subscription::query()->count())->toBe(1)
        ->and(SubscriptionItem::query()->count())->toBe(1);

    $workspace->delete();

    expect(Project::query()->pluck('id')->all())->toBe([$theirs->id])
        ->and(FeatureOverride::query()->sole()->scope_id)->toBe((string) $other->id)
        ->and(Subscription::query()->count())->toBe(0)
        ->and(SubscriptionItem::query()->count())->toBe(0)
        ->and(Teams::membersOf($other)->count())->toBe(1)
        ->and(DB::table('workspace_user')->where('workspace_id', $workspace->id)->count())->toBe(0)
        ->and(DB::table('team_invitations')->count())->toBe(0);
});

it('cancels the Stripe subscription of a workspace that is deleted', function () {
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();

    $stripe = fakeStripe(['DELETE /v1/subscriptions/sub_test' => ['id' => 'sub_test', 'object' => 'subscription', 'status' => 'canceled']]);

    $workspace->delete();

    expect($stripe->requests)->toBe(['DELETE /v1/subscriptions/sub_test'])
        ->and(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
});

it('keeps a workspace whose Stripe subscription could not be cancelled', function () {
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    Project::factory()->for($workspace)->create();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();

    fakeStripe(['DELETE /v1/subscriptions/sub_test' => ['error' => ['message' => 'Stripe is having a moment.']]]);

    expect(fn () => $workspace->delete())->toThrow(ApiErrorException::class);

    // Nothing is removed while Stripe would keep charging for it.
    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and($workspace->projects()->count())->toBe(1)
        ->and($workspace->subscriptions()->count())->toBe(1);
});

it('deletes a workspace whose subscription Stripe no longer has', function () {
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();

    fakeStripe(['DELETE /v1/subscriptions/sub_test' => ['error' => ['code' => 'resource_missing', 'message' => 'No such subscription: sub_test']]]);

    $workspace->delete();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse()
        ->and(Subscription::query()->count())->toBe(0);
});

it('removes nothing when deleting the workspace itself fails', function () {
    $workspace = workspace('pro');
    Project::factory()->count(2)->for($workspace)->create();
    Features::override($workspace, 'projects', 100);

    // Runs after the hook has removed what the workspace owns.
    Workspace::deleted(fn () => throw new RuntimeException('The database went away.'));

    expect(fn () => $workspace->delete())->toThrow(RuntimeException::class);

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and($workspace->projects()->count())->toBe(2)
        ->and(FeatureOverride::query()->count())->toBe(1);
});

it('deletes a workspace without asking Stripe when there is no Stripe secret', function () {
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();

    $workspace->delete();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
});

it('lets an owner delete the workspace after typing its name', function () {
    $workspace = workspace('pro');
    Project::factory()->count(2)->for($workspace)->create();
    $other = workspace();
    $owner = Teams::ownersOf($workspace)->first();
    Teams::addMember($other, $owner, 'member');

    inWorkspace($workspace, $owner);

    livewire(EditWorkspace::class)
        ->assertSee('Delete workspace')
        ->callAction('delete', ['name' => 'Not the name'])
        ->assertHasActionErrors(['name' => 'in']);

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();

    livewire(EditWorkspace::class)
        ->callAction('delete', ['name' => $workspace->name])
        ->assertHasNoActionErrors()
        ->assertNotified('Workspace deleted')
        ->assertRedirect('/app');

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse()
        ->and(Project::query()->count())->toBe(0);

    // They land in the workspace they still have.
    get('/app')->assertRedirect("/app/{$other->slug}");
});

it('keeps Delete workspace from admins', function () {
    $workspace = workspace();

    // Members cannot open the settings page at all.
    inWorkspace($workspace, memberOf($workspace, 'admin'));

    livewire(EditWorkspace::class)
        ->assertDontSee('Delete workspace')
        ->assertActionHidden('delete')
        ->call('mountAction', 'delete', ['name' => $workspace->name]);

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();
});

it('refuses Delete workspace to an owner who lost the role while the page was open', function () {
    $workspace = workspace();
    $owner = Teams::ownersOf($workspace)->first();
    $other = memberOf($workspace, 'owner');

    inWorkspace($workspace, $owner);

    $page = livewire(EditWorkspace::class)->assertActionVisible('delete');

    Teams::changeRole($workspace, $owner, 'admin');

    // Asked for by the browser all the same, the action is gone and nothing is deleted.
    $page->call('mountAction', 'delete', ['name' => $workspace->name])
        ->call('callMountedAction')
        ->assertNoRedirect();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue()
        ->and(Teams::isMember($workspace, $other))->toBeTrue();
});

it('cancels the subscription before deleting, and keeps the workspace when Stripe refuses', function () {
    Exceptions::fake();
    $workspace = workspace('free');
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $workspace, 'price_pro'))->assertOk();

    fakeStripe(['DELETE /v1/subscriptions/sub_test' => ['error' => ['message' => 'Stripe is having a moment.']]]);

    inWorkspace($workspace->fresh());

    livewire(EditWorkspace::class)
        ->callAction('delete', ['name' => $workspace->name])
        ->assertNotified('The subscription could not be cancelled, so the workspace was kept')
        ->assertNoRedirect();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();
    Exceptions::assertReported(fn (InvalidRequestException $exception): bool => $exception->getMessage() === 'Stripe is having a moment.');

    $stripe = fakeStripe(['DELETE /v1/subscriptions/sub_test' => ['id' => 'sub_test', 'object' => 'subscription', 'status' => 'canceled']]);

    livewire(EditWorkspace::class)
        ->callAction('delete', ['name' => $workspace->name])
        ->assertRedirect('/app');

    expect($stripe->requests)->toBe(['DELETE /v1/subscriptions/sub_test'])
        ->and(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
});

it('never deletes or exports a workspace during a support login', function () {
    $workspace = workspace();
    $owner = Teams::ownersOf($workspace)->first();

    inAdminPanel();
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($owner));

    inWorkspace($workspace, $owner);

    expect($owner->can('delete', $workspace))->toBeFalse()
        ->and($owner->can('export', $workspace))->toBeFalse();

    livewire(EditWorkspace::class)
        ->assertDontSee('Delete workspace')
        ->assertDontSee('Export workspace data')
        ->assertActionHidden('delete')
        ->assertActionHidden('export');

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeTrue();
});
