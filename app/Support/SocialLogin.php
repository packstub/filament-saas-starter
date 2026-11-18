<?php

namespace App\Support;

/**
 * Signing in with Google or GitHub (Laravel Socialite). A provider is on
 * once its client ID and secret are set in config/services.php (from
 * .env); with none set, no button shows anywhere and the routes answer 404.
 */
final class SocialLogin
{
    /** The providers the app knows, with the name on their button. */
    public const PROVIDERS = [
        'google' => 'Google',
        'github' => 'GitHub',
    ];

    /**
     * The providers that are set up, as key => label.
     *
     * @return array<string, string>
     */
    public static function enabled(): array
    {
        return array_filter(
            self::PROVIDERS,
            fn (string $label, string $provider): bool => filled(config("services.{$provider}.client_id")) && filled(config("services.{$provider}.client_secret")),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    public static function isEnabled(string $provider): bool
    {
        return array_key_exists($provider, self::enabled());
    }
}
