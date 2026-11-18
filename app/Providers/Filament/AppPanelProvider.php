<?php

namespace App\Providers\Filament;

use App\Filament\App\Pages\Auth\EditProfile;
use App\Filament\App\Pages\Tenancy\EditWorkspace;
use App\Filament\App\Pages\Tenancy\RegisterWorkspace;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Plans;
use App\Support\SupportLogins;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Packstub\Features\Facades\Features;
use Packstub\Teams\Facades\Teams;
use Packstub\Teams\Filament\Pages\Auth\Register;
use Packstub\Teams\TeamsPlugin;

/**
 * The panel your customers use, at /app/{workspace}. Everything in it is
 * scoped to the current workspace.
 */
class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('app')
            ->path('app')
            ->colors(['primary' => Color::Indigo])
            ->login()
            // Filament's registration page, with the invited e-mail filled in for people who follow an invitation.
            ->registration(Register::class)
            ->passwordReset()
            // An address has to be proven before the panel opens: invitations are matched by e-mail.
            ->emailVerification()
            // The same goes for a new address on the Profile page: it replaces the old one only after its link is followed.
            ->emailChangeVerification()
            // Filament's profile page, with Delete account added.
            ->profile(EditProfile::class)
            // Two-factor authentication with an authenticator app, switched on by each person on their Profile page.
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            // People who already have an account see their invitations in the bell menu.
            ->databaseNotifications()
            ->tenant(Workspace::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterWorkspace::class)
            ->tenantProfile(EditWorkspace::class)
            ->navigationGroups(['Workspace'])
            ->discoverResources(in: app_path('Filament/App/Resources'), for: 'App\Filament\App\Resources')
            ->discoverPages(in: app_path('Filament/App/Pages'), for: 'App\Filament\App\Pages')
            ->discoverWidgets(in: app_path('Filament/App/Widgets'), for: 'App\Filament\App\Widgets')
            ->pages([Dashboard::class])
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
                // Members and Invitations pages, roles from config/packstub-teams.php.
                TeamsPlugin::make()
                    ->roleProvider('simple')
                    ->navigationGroup('Workspace')
                    // Signing up creates a first workspace; following an invitation joins that workspace instead.
                    ->personalTeams(fn (User $user): Workspace => Workspace::named(Teams::personalTeamName($user)))
                    // Seats come from the plan, like every other limit.
                    ->seatLimit(fn (Workspace $workspace): ?int => Features::limit('seats', $workspace)),
                // The "Your plan" page, with usage against each limit.
                Plans::plugin()
                    ->navigationGroup('Workspace'),
                SupportLogins::plugin(),
            ]);
    }
}
