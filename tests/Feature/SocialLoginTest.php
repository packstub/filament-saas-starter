<?php

use App\Filament\Admin\Resources\Users\Pages\ManageUsers;
use App\Filament\App\Pages\Auth\EditProfile;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\DataExport;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as ProviderUser;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

beforeEach(function () {
    config()->set('services.google', ['client_id' => 'google-id', 'client_secret' => 'google-secret', 'redirect' => '/auth/google/callback']);
    config()->set('services.github', ['client_id' => 'github-id', 'client_secret' => 'github-secret', 'redirect' => '/auth/github/callback']);
});

/**
 * The account Google or GitHub sends back: Socialite answered by the test.
 */
function providerReturns(string $provider, string $id, ?string $email, bool $verified = true, string $name = 'Nina Lopez'): void
{
    $user = (new ProviderUser)->setRaw(['email_verified' => $verified])->map(['id' => $id, 'name' => $name, 'email' => $email]);

    Socialite::shouldReceive('driver')->with($provider)->andReturn(Mockery::mock(['user' => $user]));
}

it('shows a button per provider that is set up, and none without credentials', function () {
    get('/app/login')->assertSee('Continue with Google')->assertSee('Continue with GitHub');
    get('/app/register')->assertSee('Continue with Google');

    config()->set('services.github.client_secret', null);

    get('/app/login')->assertSee('Continue with Google')->assertDontSee('Continue with GitHub');

    config()->set('services.google.client_id', null);

    get('/app/login')->assertDontSee('Continue with');
    get('/auth/google/redirect')->assertNotFound();
    get('/auth/google/callback')->assertNotFound();
    get('/auth/twitter/redirect')->assertNotFound();
});

it('sends people to the provider', function () {
    get('/auth/github/redirect')->assertRedirectContains('https://github.com/login/oauth/authorize');
});

it('signs up someone new with their first workspace, verified by the provider', function () {
    providerReturns('google', 'g-1', 'Nina@Example.com');

    get('/auth/google/callback')->assertRedirect();

    $user = User::where('email', 'nina@example.com')->sole();

    assertAuthenticatedAs($user);
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->hasPassword())->toBeFalse()
        ->and($user->socialAccounts()->sole()->only('provider', 'provider_id'))->toBe(['provider' => 'google', 'provider_id' => 'g-1'])
        ->and(Teams::teamsOf($user)->count())->toBe(1);
});

it('signs in the user an account is connected to', function () {
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42', 'email' => 'old@example.com']);
    providerReturns('github', '42', 'whatever@example.com');

    get('/auth/github/callback')->assertRedirect();

    assertAuthenticatedAs($user);
});

it('connects a new account to the user with the same verified address', function () {
    $user = User::factory()->create(['email' => 'nina@example.com']);
    providerReturns('google', 'g-1', 'nina@example.com');

    get('/auth/google/callback');

    assertAuthenticatedAs($user);
    expect($user->socialAccounts()->count())->toBe(1)->and(User::count())->toBe(1);
});

it('never connects to a user who has not verified their address', function () {
    // Someone signed up with this address without owning it, and set a password they know.
    $user = User::factory()->unverified()->create(['email' => 'nina@example.com']);
    providerReturns('google', 'g-1', 'nina@example.com');

    get('/auth/google/callback')->assertRedirect('/app/login');

    assertGuest();
    expect($user->socialAccounts()->count())->toBe(0)->and(User::count())->toBe(1);
});

it('never trusts an address the provider has not verified', function () {
    User::factory()->create(['email' => 'nina@example.com']);
    providerReturns('google', 'g-1', 'nina@example.com', verified: false);

    get('/auth/google/callback')->assertRedirect('/app/login');

    assertGuest();
    expect(SocialAccount::count())->toBe(0);
});

it('refuses an account without an address', function () {
    providerReturns('github', '7', null);

    get('/auth/github/callback')->assertRedirect('/app/login');

    assertGuest();
    expect(User::count())->toBe(0);
});

it('never skips two-factor authentication', function (User $user) {
    $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '42']);
    providerReturns('github', '42', $user->email);

    get('/auth/github/callback')->assertRedirect('/app/login');

    assertGuest();
})->with([
    'an authenticator app' => fn () => User::factory()->withAppAuthentication()->create(),
    'an admin' => fn () => User::factory()->admin()->create(),
]);

it('takes people back to sign in when the provider fails', function () {
    Socialite::shouldReceive('driver')->with('google')->andReturn(Mockery::mock(['user' => null])->shouldReceive('user')->andThrow(new InvalidStateException)->getMock());

    get('/auth/google/callback')->assertRedirect('/app/login');

    assertGuest();
});

it('joins the workspace of the invitation that brought someone new', function () {
    $workspace = workspace('pro');
    $invitation = Teams::invite($workspace, 'nina@example.com', send: false);
    get(Teams::acceptUrl($invitation));
    providerReturns('google', 'g-1', 'nina@example.com');

    get('/auth/google/callback');

    $user = User::where('email', 'nina@example.com')->sole();

    expect(Teams::teamsOf($user)->pluck('workspaces.id')->all())->toBe([$workspace->getKey()]);
});

it('connects an account from the Profile page, unless it belongs to someone else', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $other->socialAccounts()->create(['provider' => 'github', 'provider_id' => 'taken']);

    actingAs($user);

    providerReturns('github', 'taken', 'x@example.com');
    get('/auth/github/callback')->assertRedirect(EditProfile::getUrl(panel: 'app'));
    expect($user->socialAccounts()->count())->toBe(0);

    providerReturns('google', 'g-9', 'someone-else@example.com');
    get('/auth/google/callback')->assertRedirect(EditProfile::getUrl(panel: 'app'));
    expect($user->socialAccounts()->sole()->provider)->toBe('google');
});

describe('the Profile page', function () {
    beforeEach(fn () => Filament::setCurrentPanel('app'));

    it('offers to connect each provider and to disconnect a connected one', function () {
        $user = User::factory()->create();
        $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '1', 'email' => 'nina@github.test']);
        actingAs($user);

        livewire(EditProfile::class)
            ->assertSee('Connected accounts')
            ->assertSee('Connect Google')
            ->assertSee('Disconnect GitHub (nina@github.test)')
            ->callAction(TestAction::make('disconnect')->arguments(['provider' => 'github']));

        expect($user->socialAccounts()->count())->toBe(0);
    });

    it('never removes the last way to sign in', function () {
        $user = User::factory()->withoutPassword()->create();
        $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '1']);
        actingAs($user);

        livewire(EditProfile::class)
            ->assertActionDisabled(TestAction::make('disconnect')->arguments(['provider' => 'github']))
            ->callAction(TestAction::make('disconnect')->arguments(['provider' => 'github']));

        expect($user->socialAccounts()->count())->toBe(1);
    });

    it('lets someone without a password choose one without the current one', function () {
        $user = User::factory()->withoutPassword()->create();
        actingAs($user);

        livewire(EditProfile::class)
            ->fillForm(['password' => 'a-new-password', 'passwordConfirmation' => 'a-new-password'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($user->fresh()->hasPassword())->toBeTrue();
    });

    it('still asks for the current password when there is one', function () {
        $user = User::factory()->create();
        actingAs($user);

        livewire(EditProfile::class)
            ->fillForm(['password' => 'a-new-password', 'passwordConfirmation' => 'a-new-password'])
            ->call('save')
            ->assertHasFormErrors(['currentPassword']);
    });

    it('deletes an account without a password once the address is typed', function () {
        $user = User::factory()->withoutPassword()->create(['email' => 'nina@example.com']);
        $user->socialAccounts()->create(['provider' => 'github', 'provider_id' => '1']);
        actingAs($user);

        livewire(EditProfile::class)
            ->callAction('deleteAccount', ['email' => 'wrong@example.com'])
            ->assertHasActionErrors(['email']);

        expect($user->fresh())->not->toBeNull();

        livewire(EditProfile::class)->callAction('deleteAccount', ['email' => 'nina@example.com']);

        expect(User::find($user->getKey()))->toBeNull()
            ->and(SocialAccount::count())->toBe(0);
    });

    it('hides the section while no provider is set up and nothing is connected', function () {
        config()->set('services.google.client_id', null);
        config()->set('services.github.client_id', null);
        actingAs(User::factory()->create());

        livewire(EditProfile::class)->assertDontSee('Connected accounts');
    });
});

it('lists connected accounts in the personal export', function () {
    $user = User::factory()->create();
    $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => 'g-1', 'email' => 'nina@gmail.test']);

    expect(DataExport::forUser($user)['connected_accounts'])->toHaveCount(1)
        ->and(DataExport::forUser($user)['connected_accounts'][0])->toMatchArray(['provider' => 'google', 'email' => 'nina@gmail.test'])
        ->not->toHaveKey('provider_id');
});

it('never connects an account or starts a sign-in during a support login', function () {
    $owner = User::factory()->create();
    $workspace = workspace('pro', $owner);

    inAdminPanel(admin());
    livewire(ManageUsers::class)->callAction(TestAction::make('impersonate')->table($owner));
    inWorkspace($workspace, $owner);
    expect(AccountSwitcher::isImpersonating())->toBeTrue();

    // The admin's own GitHub, which would let them sign in as the customer later.
    providerReturns('github', 'admins-github', 'admin-personal@example.com');

    // The browser's session, which holds the support login. Refused with a 403, which
    // Account Switcher answers right after a switch with a redirect back into the workspace.
    $this->withSession(session()->all());

    $redirect = $this->get('/auth/github/redirect');
    $this->get('/auth/github/callback');

    expect($redirect->isForbidden() || ! str_contains((string) $redirect->headers->get('Location'), 'github.com'))->toBeTrue();
    expect($owner->socialAccounts()->count())->toBe(0)
        ->and(SocialAccount::count())->toBe(0);
});

it('finds the user whatever case their address was typed in', function () {
    $user = User::factory()->create(['email' => 'Nina@Example.com']);
    providerReturns('google', 'g-1', 'nina@example.com');

    get('/auth/google/callback');

    assertAuthenticatedAs($user);
    expect(User::count())->toBe(1)
        ->and($user->socialAccounts()->count())->toBe(1);
});
