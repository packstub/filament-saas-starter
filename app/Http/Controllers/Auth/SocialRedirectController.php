<?php

namespace App\Http\Controllers\Auth;

use App\Support\SocialLogin;
use Laravel\Socialite\Facades\Socialite;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * "Continue with Google / GitHub": off to the provider, which sends the
 * person back to SocialCallbackController. Someone already signed in
 * comes here from their Profile page to connect the account instead;
 * never during a support login.
 */
class SocialRedirectController
{
    public function __invoke(string $provider): RedirectResponse
    {
        abort_unless(SocialLogin::isEnabled($provider), 404);

        // Support signed in as a customer never adds a way into the customer's account.
        abort_if(AccountSwitcher::isImpersonating(), 403);

        return Socialite::driver($provider)->redirect();
    }
}
