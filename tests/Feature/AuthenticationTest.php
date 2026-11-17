<?php

use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Auth\Pages\EditProfile;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Packstub\Teams\Filament\Pages\Auth\Register;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Livewire\livewire;

/**
 * Fill in the sign-up form with a password and submit it.
 */
function signUpWithPassword(string $password)
{
    return livewire(Register::class)
        ->fillForm(['name' => 'Nina', 'email' => 'nina@example.com', 'password' => $password, 'passwordConfirmation' => $password])
        ->call('register');
}

it('asks for at least eight characters outside production', function () {
    signUpWithPassword('short')->assertHasFormErrors(['password']);

    expect(User::query()->count())->toBe(0);

    signUpWithPassword('password')->assertHasNoFormErrors();
});

it('asks for at least twelve characters in production', function () {
    app()->detectEnvironment(fn (): string => 'production');

    signUpWithPassword('elevenchars')->assertHasFormErrors(['password']);

    expect(User::query()->count())->toBe(0)
        ->and(Password::default()->appliedRules())->toMatchArray(['min' => 12, 'uncompromised' => true]);
});

it('holds a new password on the Profile page to the same rules', function () {
    $user = User::factory()->create();

    inWorkspace(workspace('free', $user), $user);

    livewire(EditProfile::class)
        ->fillForm(['password' => 'short', 'passwordConfirmation' => 'short', 'currentPassword' => 'password'])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('signs someone in with their password while they have no authenticator app', function () {
    $user = User::factory()->create();

    livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    assertAuthenticatedAs($user);
});

it('asks for the code of the authenticator app once someone has set one up', function () {
    $user = User::factory()->withAppAuthentication()->create();

    $page = livewire(Login::class)
        ->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    // The password alone does not sign them in.
    assertGuest();

    $page->fillForm(['app.code' => '000000'], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasFormErrors(['app.code'], 'multiFactorChallengeForm');

    assertGuest();

    $page->fillForm(['app.code' => AppAuthentication::make()->getCurrentCode($user)], 'multiFactorChallengeForm')
        ->call('authenticate')
        ->assertHasNoFormErrors([], 'multiFactorChallengeForm');

    assertAuthenticatedAs($user);
});

it('offers two-factor authentication on the Profile page of the app panel', function () {
    $user = User::factory()->create();

    inWorkspace(workspace('free', $user), $user);

    livewire(EditProfile::class)->assertSee(__('filament-panels::auth/multi-factor/app/provider.management_schema.actions.label'));

    expect(Filament::getPanel('app')->isMultiFactorAuthenticationRequired())->toBeFalse();
});

it('sends an admin without two-factor authentication to set it up', function () {
    $admin = User::factory()->admin()->create();

    actingAs($admin)
        ->get('/admin/users')
        ->assertRedirect(route('filament.admin.auth.multi-factor-authentication.set-up-required'));

    get(route('filament.admin.auth.multi-factor-authentication.set-up-required'))->assertOk();

    expect(Filament::getPanel('admin')->isMultiFactorAuthenticationRequired())->toBeTrue();
});

it('opens the admin panel to an admin with two-factor authentication', function () {
    actingAs(admin())->get('/admin/users')->assertOk();
});

it('stores the two-factor secret and recovery codes encrypted, and keeps them out of arrays', function () {
    $user = User::factory()->withAppAuthentication()->create();
    $user->saveAppAuthenticationRecoveryCodes(['one', 'two']);

    $row = DB::table('users')->where('id', $user->id)->first();

    expect($row->app_authentication_secret)->not->toContain($user->getAppAuthenticationSecret())
        ->and($row->app_authentication_recovery_codes)->not->toContain('one')
        ->and($user->fresh()->getAppAuthenticationRecoveryCodes())->toBe(['one', 'two'])
        ->and($user->fresh()->toArray())->not->toHaveKeys(['password', 'app_authentication_secret', 'app_authentication_recovery_codes']);
});
