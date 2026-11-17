<?php

namespace App\Providers\Filament;

use App\Support\Plans;
use App\Support\SupportLogins;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Your own back office, at /admin: every user and workspace, feature flags
 * and per-workspace overrides, and support logins. Only users with
 * `is_admin` get in (User::canAccessPanel()), and only with two-factor
 * authentication on their account.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->brandName(config('app.name').' Admin')
            ->colors(['primary' => Color::Slate])
            ->login()
            // An admin can sign in as any customer, so a password alone is not enough here: an admin
            // without an authenticator app is sent to set one up first. Not in the local environment,
            // where the one-click developer logins skip the password anyway.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: ! app()->isLocal())
            ->navigationGroups(['Customers', 'Plans'])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                // Feature flags and overrides instead of the customer-facing plan page.
                Plans::plugin()
                    ->planPage(null)
                    ->adminPages()
                    ->navigationGroup('Plans'),
                SupportLogins::plugin(),
            ]);
    }
}
