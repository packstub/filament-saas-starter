<?php

use App\Models\User;
use Filament\Auth\Notifications\VerifyEmailChange;
use Filament\Auth\Pages\EditProfile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Packstub\Teams\Exceptions\SeatLimitReached;
use Packstub\Teams\Facades\Teams;
use Packstub\Teams\Filament\Pages\Auth\Register;
use Packstub\Teams\Filament\Pages\Members;
use Packstub\Teams\Mail\TeamInvitationMail;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function () {
    Mail::fake();
});

it('sends an invitation from the Members page', function () {
    $workspace = workspace('pro');

    inWorkspace($workspace);

    livewire(Members::class)
        ->callTableAction('invite', data: ['emails' => ['nina@example.com'], 'role' => 'member'])
        ->assertHasNoTableActionErrors();

    $invitation = $workspace->pendingInvitations()->sole();

    expect($invitation->email)->toBe('nina@example.com')
        ->and($invitation->role)->toBe('member');

    // Queued: sent during the request while QUEUE_CONNECTION is `sync`, by the worker once it is not.
    Mail::assertQueued(TeamInvitationMail::class, fn (TeamInvitationMail $mail) => $mail->hasTo('nina@example.com'));
});

it('lets an invited person sign up straight into the workspace', function () {
    $workspace = workspace('pro');
    $invitation = Teams::invite($workspace, 'nina@example.com', 'admin');

    // The link in the e-mail: a guest is sent to the sign-in page, with the invitation kept for after.
    get($invitation->acceptUrl())->assertRedirect('/app/login');

    livewire(Register::class)
        ->fillForm(['name' => 'Nina', 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
        ->call('register')
        ->assertHasNoFormErrors();

    $nina = User::where('email', 'nina@example.com')->sole();

    // She joins the workspace that invited her, and gets no workspace of her own.
    expect($nina->joinedTeams()->sole()->is($workspace))->toBeTrue()
        ->and($nina->teamRole($workspace))->toBe('admin')
        ->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('lets someone with an account accept an invitation', function () {
    $workspace = workspace('pro');
    $ethan = User::factory()->create(['email' => 'ethan@example.com']);
    $invitation = Teams::invite($workspace, 'ethan@example.com');

    actingAs($ethan)
        ->get($invitation->acceptUrl())
        ->assertRedirect("/app/{$workspace->slug}");

    expect($ethan->teamRole($workspace))->toBe('member');
});

it('keeps an invitation from an account that never proved its e-mail address', function () {
    // Mallory signs up with an address that is not hers and cannot verify it.
    livewire(Register::class)
        ->fillForm(['name' => 'Mallory', 'email' => 'bob@corp.example', 'password' => 'a-long-password', 'passwordConfirmation' => 'a-long-password'])
        ->call('register')
        ->assertHasNoFormErrors();

    $mallory = User::where('email', 'bob@corp.example')->sole();

    // Later, Corp invites the real Bob as an admin. The link goes to his mailbox.
    $corp = workspace('pro');
    Teams::invite($corp, 'bob@corp.example', 'admin');

    // The invitation is also listed under the bell of the account with that
    // address, which is inside the panel: closed until the address is verified.
    $own = $mallory->joinedTeams()->sole()->slug;

    actingAs($mallory);

    get("/app/{$own}")->assertRedirect(route('filament.app.auth.email-verification.prompt'));
    get("/app/{$own}/members")->assertRedirect(route('filament.app.auth.email-verification.prompt'));
    get("/app/{$own}/projects")->assertRedirect(route('filament.app.auth.email-verification.prompt'));

    actingAs($mallory)->get("/app/{$corp->slug}")->assertNotFound();

    expect($mallory->teamRole($corp))->toBeNull()
        ->and($corp->pendingInvitations()->count())->toBe(1);
});

it('keeps an invitation from someone who types the invited address into their profile', function () {
    Notification::fake();

    // Mallory has a verified account of her own, and claims Bob's address on the Profile page.
    $mallory = User::factory()->create(['email' => 'mallory@example.com']);

    inWorkspace(workspace('free', $mallory), $mallory);

    livewire(EditProfile::class)
        ->fillForm(['email' => 'bob@corp.example', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasNoFormErrors();

    // The address changes only through the link sent to it, which she never sees.
    expect($mallory->fresh()->email)->toBe('mallory@example.com');

    Notification::assertSentOnDemand(VerifyEmailChange::class);

    // Corp then invites the real Bob. Nothing reaches Mallory's account, and the link is not hers to accept.
    $corp = workspace('pro');
    $invitation = Teams::invite($corp, 'bob@corp.example', 'admin');

    actingAs($mallory)->get($invitation->acceptUrl());

    expect($mallory->fresh()->teamRole($corp))->toBeNull()
        ->and($corp->pendingInvitations()->count())->toBe(1);
});

it('lets only owners and admins invite', function () {
    $workspace = workspace('pro');

    inWorkspace($workspace, memberOf($workspace, 'member'));

    livewire(Members::class)->assertTableActionHidden('invite');

    expect($workspace->pendingInvitations()->count())->toBe(0);
});

it('refuses an invitation that has expired', function () {
    $workspace = workspace('pro');
    $late = User::factory()->create(['email' => 'late@example.com']);
    $invitation = Teams::invite($workspace, 'late@example.com', send: false);
    $invitation->forceFill(['expires_at' => now()->subDay()])->save();

    actingAs($late)->get($invitation->acceptUrl());

    expect($late->teamRole($workspace))->toBeNull();
});

it('refuses an invitation link into a workspace without a free seat', function () {
    $workspace = workspace('free');
    Teams::addMember($workspace, User::factory()->create(), 'member');
    $link = Teams::createInviteLink($workspace);
    $visitor = User::factory()->create();

    actingAs($visitor)->get($link->acceptUrl());

    expect($visitor->teamRole($workspace))->toBeNull()
        ->and(Teams::membersOf($workspace)->count())->toBe(2);
});

it('stops inviting when the seats of the plan are taken', function () {
    $workspace = workspace('free');

    // Free has two seats: the owner and one invitation.
    Teams::invite($workspace, 'one@example.com');

    expect(fn () => Teams::invite($workspace, 'two@example.com'))->toThrow(SeatLimitReached::class);

    $workspace->changePlan('pro');

    expect(Teams::invite($workspace, 'two@example.com')->exists)->toBeTrue();
});

it('keeps every member when a workspace moves to a plan with fewer seats, and stops inviting', function () {
    $workspace = workspace('pro');
    memberOf($workspace, 'admin');
    memberOf($workspace, 'member');

    $workspace->changePlan('free');

    // Three members on a plan with two seats: nobody is removed, nobody new comes in.
    expect(Teams::membersOf($workspace)->count())->toBe(3)
        ->and(Teams::hasAvailableSeats($workspace))->toBeFalse()
        ->and(fn () => Teams::invite($workspace, 'nina@example.com'))->toThrow(SeatLimitReached::class);
});
