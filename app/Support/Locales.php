<?php

namespace App\Support;

/**
 * The languages the app speaks (`locales` in config/app.php), each with a
 * folder in lang/.
 */
final class Locales
{
    /**
     * @return array<string, string> locale => its name in that language
     */
    public static function all(): array
    {
        return config('app.locales');
    }

    public static function supports(?string $locale): bool
    {
        return $locale !== null && array_key_exists($locale, self::all());
    }
}
