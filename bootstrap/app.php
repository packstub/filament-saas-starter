<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Stripe's webhooks carry a signature instead of a CSRF token.
        $middleware->preventRequestForgery(except: ['stripe/*']);

        // There is no `login` route: guests go to the app panel's sign-in page (the data exports
        // are behind `auth`), and the API answers 401 instead of redirecting anywhere.
        $middleware->redirectGuestsTo(fn (Request $request): ?string => $request->is('api/*') ? null : route('filament.app.auth.login'));

        // What an API token may do, checked per route in routes/api.php.
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The API answers in JSON, errors included, whatever the client asks for.
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*'));
    })->create();
