<?php

namespace App\Http\Controllers\Auth;

use App\Support\SocialLogin;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * "Continue with Google / GitHub": off to the provider, which sends the
 * person back to SocialCallbackController. Someone already signed in
 * comes here from their Profile page to connect the account instead.
 */
class SocialRedirectController
{
    public function __invoke(string $provider): RedirectResponse
    {
        abort_unless(SocialLogin::isEnabled($provider), 404);

        return Socialite::driver($provider)->redirect();
    }
}
