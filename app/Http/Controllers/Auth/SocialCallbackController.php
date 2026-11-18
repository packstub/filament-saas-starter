<?php

namespace App\Http\Controllers\Auth;

use App\Filament\App\Pages\Auth\EditProfile;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialLogin;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as ProviderUser;
use Laravel\Socialite\Facades\Socialite;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Throwable;

/**
 * Where Google or GitHub sends the person back.
 *
 * - Signed in already: the account is connected to theirs (from the Profile page).
 * - An account already connected: its user is signed in.
 * - A new account whose verified e-mail address belongs to a user with a
 *   verified address: connected to that user and signed in. An address
 *   the provider has not verified, or a user who has not verified theirs,
 *   is never linked: whoever controls one side could take over the other.
 * - Nobody with that address: a new user (verified by the provider) with
 *   their first workspace, or the workspace of the invitation that brought
 *   them here (Filament Teams listens to `Registered` and `Login`).
 *
 * Never during a support login: the admin is not the account's owner.
 *
 * Two-factor authentication is never skipped: someone who has it on, and
 * every admin, signs in with their password and code instead.
 */
class SocialCallbackController
{
    public function __invoke(Request $request, string $provider): RedirectResponse
    {
        abort_unless(SocialLogin::isEnabled($provider), 404);

        // An admin signed in as a customer could connect their own Google or GitHub to the
        // customer's account and sign in as them later, outside any support login.
        abort_if(AccountSwitcher::isImpersonating(), 403);

        $label = SocialLogin::PROVIDERS[$provider];

        try {
            $account = Socialite::driver($provider)->user();
        } catch (Throwable $exception) {
            // A cancelled consent screen, an expired state, the provider down.
            report($exception);

            return $this->refuse("Signing in with {$label} did not work. Please try again.");
        }

        if ($user = Filament::auth()->user()) {
            return $this->connect($user, $provider, $account);
        }

        $linked = SocialAccount::query()->where('provider', $provider)->where('provider_id', $account->getId())->first();

        if ($linked) {
            return $this->signIn($linked->user, $provider);
        }

        $email = $this->verifiedEmail($provider, $account);

        if ($email === null) {
            return $this->refuse("{$label} did not share a verified e-mail address with us. Sign up with your e-mail address instead.");
        }

        // Whatever case the address was typed in at sign-up, as Filament Teams matches invitations:
        // `=` is case-sensitive on SQLite and PostgreSQL, which would make a second account.
        $user = User::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($user && ! $user->hasVerifiedEmail()) {
            return $this->refuse("An account with {$email} exists but its address is not verified yet. Sign in with your password and follow the link we sent you first.");
        }

        if (! $user) {
            $user = DB::transaction(function () use ($account, $email): User {
                $user = User::create(['name' => $account->getName() ?: $account->getNickname() ?: $email, 'email' => $email]);
                $user->forceFill(['email_verified_at' => now()])->save();

                return $user;
            });

            $this->link($user, $provider, $account);

            // Creates their first workspace, or joins the one an invitation brought them to.
            event(new Registered($user));

            return $this->signIn($user, $provider);
        }

        $this->link($user, $provider, $account);

        return $this->signIn($user, $provider);
    }

    /**
     * From the Profile page: add the account to the signed-in user, unless it belongs to someone else.
     */
    protected function connect(User $user, string $provider, ProviderUser $account): RedirectResponse
    {
        $label = SocialLogin::PROVIDERS[$provider];
        $owner = SocialAccount::query()->where('provider', $provider)->where('provider_id', $account->getId())->value('user_id');

        if ($owner !== null && $owner !== $user->getKey()) {
            Notification::make()->danger()->title("That {$label} account is already connected to another user.")->send();
        } else {
            $this->link($user, $provider, $account);

            Notification::make()->success()->title("{$label} is connected. You can sign in with it from now on.")->send();
        }

        return redirect()->to(EditProfile::getUrl(panel: 'app'));
    }

    protected function link(User $user, string $provider, ProviderUser $account): void
    {
        $user->socialAccounts()->updateOrCreate(
            ['provider' => $provider],
            ['provider_id' => (string) $account->getId(), 'email' => $account->getEmail()],
        );
    }

    protected function signIn(User $user, string $provider): RedirectResponse
    {
        if ($user->is_admin || filled($user->getAppAuthenticationSecret())) {
            return $this->refuse('This account uses two-factor authentication. Sign in with your password and your code.');
        }

        Filament::auth()->login($user);
        session()->regenerate();

        return redirect()->intended(Filament::getPanel('app')->getUrl());
    }

    /**
     * The address, if the provider has verified it: Google says so, and
     * Socialite asks GitHub for the primary address only once it is verified.
     */
    protected function verifiedEmail(string $provider, ProviderUser $account): ?string
    {
        $email = $account->getEmail();

        $verified = match ($provider) {
            'google' => ($account->getRaw()['email_verified'] ?? false) === true,
            'github' => true,
            default => false,
        };

        return $verified && filled($email) ? strtolower($email) : null;
    }

    protected function refuse(string $message): RedirectResponse
    {
        Notification::make()->danger()->title($message)->persistent()->send();

        return redirect()->to(Filament::getPanel('app')->getLoginUrl());
    }
}
