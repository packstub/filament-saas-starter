<?php

use App\Filament\App\Pages\Tenancy\EditWorkspace;
use App\Models\Workspace;
use Filament\Actions\Testing\TestAction;
use Packstub\Teams\Facades\Teams;
use Packstub\Teams\Filament\Pages\Members;

use function Pest\Livewire\livewire;

it('lets an owner hand the workspace to another member, who can then delete it', function () {
    $workspace = workspace();
    $owner = Teams::ownersOf($workspace)->first();
    $member = memberOf($workspace, 'member');

    inWorkspace($workspace, $owner);

    livewire(Members::class)
        ->callAction(TestAction::make('transferOwnership')->table($member))
        ->assertNotified();

    expect(Teams::roleOf($workspace, $member))->toBe('owner')
        // The former owner stays on as an admin.
        ->and(Teams::roleOf($workspace, $owner))->toBe('admin')
        ->and($owner->can('delete', $workspace))->toBeFalse()
        ->and($member->can('delete', $workspace))->toBeTrue();

    inWorkspace($workspace, $member);

    livewire(EditWorkspace::class)
        ->callAction('delete', ['name' => $workspace->name])
        ->assertRedirect();

    expect(Workspace::query()->whereKey($workspace->id)->exists())->toBeFalse();
});

it('offers the transfer to owners only', function () {
    $workspace = workspace('pro');
    $admin = memberOf($workspace, 'admin');
    $member = memberOf($workspace, 'member');

    inWorkspace($workspace, $admin);

    livewire(Members::class)
        ->assertActionHidden(TestAction::make('transferOwnership')->table($member));
});

it('sends Stripe receipts to the new owner', function () {
    $workspace = workspace();
    $workspace->forceFill(['stripe_id' => 'cus_test'])->save();
    $owner = Teams::ownersOf($workspace)->first();
    $member = memberOf($workspace, 'member');

    $stripe = fakeStripe(['POST /v1/customers/cus_test' => ['id' => 'cus_test', 'object' => 'customer', 'email' => $member->email]]);

    Teams::transferOwnership($workspace, $owner, $member);

    expect($stripe->requests)->toBe(['POST /v1/customers/cus_test'])
        ->and($stripe->params['POST /v1/customers/cus_test']['email'])->toBe($member->email)
        ->and($workspace->stripeEmail())->toBe($member->email);
});

it('leaves Stripe alone for a workspace that was never a customer', function () {
    $workspace = workspace();
    $stripe = fakeStripe();

    Teams::transferOwnership($workspace, Teams::ownersOf($workspace)->first(), memberOf($workspace, 'member'));

    expect($stripe->requests)->toBe([]);
});
