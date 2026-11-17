<?php

namespace App\Support;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Packstub\AccountSwitcher\AccountSwitcherPlugin;

/**
 * Filament Account Switcher for both panels: an admin signs in as a customer
 * from the Users table in /admin, works in the customer's panel under a
 * banner, and switches back. Who may do that is decided on the User model
 * (canImpersonate() and canBeImpersonated()); every switch is logged in the
 * `account_switches` table.
 */
final class SupportLogins
{
    public static function plugin(): AccountSwitcherPlugin
    {
        return AccountSwitcherPlugin::make()
            ->linkedAccounts(false)
            // The seeded accounts as one-click buttons on the login pages, in the local environment only.
            ->developerLogins([DatabaseSeeder::OWNER_EMAIL, DatabaseSeeder::MEMBER_EMAIL, DatabaseSeeder::ADMIN_EMAIL])
            // A customer lands in their workspace (the app panel's entry route picks it), an admin back in the admin panel.
            ->redirectTo(fn (User $user): string => $user->is_admin ? Filament::getPanel('admin')->getUrl() : route('filament.app.tenant'))
            ->banner(position: 'top');
    }
}
