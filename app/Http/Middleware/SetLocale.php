<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Speak each person's language: the one on their Profile page once they
 * are signed in, the one picked in the home page's footer before that,
 * the app's default (APP_LOCALE) otherwise.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $locale = collect([
            $user instanceof User ? $user->locale : null,
            $request->hasSession() ? $request->session()->get('locale') : null,
        ])->first(fn (?string $locale): bool => Locales::supports($locale));

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
