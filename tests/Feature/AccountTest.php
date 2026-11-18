<?php

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\App\Pages\Auth\EditProfile;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Filament\Actions\Testing\TestAction;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\assertGuest;
use function Pest\Laravel\postJson;
use function Pest\Livewire\livewire;

it('deletes the account, the workspaces only its owner used, and leaves the shared ones', function () {
    $user = User::factory()->create();

    // Alone in it, on a paid plan: deleted, and its subscription cancelled.
    $alone = workspace('free', $user);
    $alone->forceFill(['stripe_id' => 'cus_test'])->save();
    Project::factory()->for($alone)->create();
    postJson('/stripe/webhook', stripeSubscriptionEvent('created', $alone, 'price_pro'))->assertOk();

    // Shared with another owner, and someone else's: left.
    $shared = workspace('pro', $user);
    $coOwner = memberOf($shared, 'owner');
    $theirs = workspace('pro');
    Teams::addMember($theirs, $user, 'member');

    $stripe = fakeStripe(['DELETE /v1/subscriptions/sub_test' => ['id' => 'sub_test', 'object' => 'subscription', 'status' => 'canceled']]);

    inWorkspace($shared, $user);

    livewire(EditProfile::class)
        ->assertSee('Delete account')
        ->callAction('deleteAccount', ['password' => 'wrong'])
        ->assertHasActionErrors(['password']);

    expect(User::query()->whereKey($user->id)->exists())->toBeTrue();

    livewire(EditProfile::class)
        ->callAction('deleteAccount', ['password' => 'password'])
        ->assertHasNoActionErrors()
        ->assertRedirect(route('home'));

    assertGuest();

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse()
        ->and($stripe->requests)->toBe(['DELETE /v1/subscriptions/sub_test'])
        ->and(Workspace::query()->whereKey($alone->id)->exists())->toBeFalse()
        ->and(Project::query()->count())->toBe(0)
        ->and(Teams::membersOf($shared)->pluck('users.id')->all())->toBe([$coOwner->id])
        ->and(Teams::membersOf($theirs)->count())->toBe(1);
});

it('asks the only owner of a workspace others use to hand it over first', function () {
    $workspace = workspace();
    $owner = Teams::ownersOf($workspace)->first();
    memberOf($workspace, 'member');

    inWorkspace($workspace, $owner);

    expect($owner->workspacesBlockingDeletion()->pluck('name')->all())->toBe([$workspace->name]);

    // Asked for all the same, nothing is deleted.
    livewire(EditProfile::class)
        ->callAction('deleteAccount')
        ->assertNoRedirect();

    expect(User::query()->whereKey($owner->id)->exists())->toBeTrue()
        ->and(fn () => $owner->deleteAccount())->toThrow(LogicException::class);

    // Once ownership is handed over, the account can go.
    $member = Teams::membersOf($workspace)->whereKeyNot($owner->id)->first();
    Teams::transferOwnership($workspace, $owner, $member);

    expect($owner->fresh()->workspacesBlockingDeletion())->toBeEmpty();

    $owner->fresh()->deleteAccount();

    expect(User::query()->whereKey($owner->id)->exists())->toBeFalse()
        ->and(Teams::ownersOf($workspace)->pluck('users.id')->all())->toBe([$member->id]);
});

it('never deletes an account during a support login', function () {
    $workspace = workspace();
    $customer = Teams::ownersOf($workspace)->first();

    inAdminPanel();
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($customer));

    inWorkspace($workspace, $customer);

    livewire(EditProfile::class)
        ->assertDontSee('Delete account')
        ->assertActionHidden('deleteAccount');

    expect(User::query()->whereKey($customer->id)->exists())->toBeTrue();
});
