<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Packstub\Teams\Facades\Teams;

/**
 * A small demo to click through: Acme on the Pro plan with an owner, a
 * member, a pending invitation and a few projects, plus an admin for the
 * /admin panel. Safe to run again; it only adds what is missing. It never
 * runs in production: the accounts share a password everyone knows.
 *
 * The three accounts are also the one-click logins on the sign-in pages in
 * the local environment (App\Support\SupportLogins).
 */
class DatabaseSeeder extends Seeder
{
    public const OWNER_EMAIL = 'demo@example.com';

    public const MEMBER_EMAIL = 'member@example.com';

    public const ADMIN_EMAIL = 'admin@example.com';

    public const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('The demo accounts are not seeded in production.');

            return;
        }

        $owner = $this->user(self::OWNER_EMAIL, 'Olivia Bennett');
        $member = $this->user(self::MEMBER_EMAIL, 'Ethan Park');
        $this->user(self::ADMIN_EMAIL, 'Sam Carter', admin: true);

        $acme = Workspace::firstOrCreate(['slug' => 'acme'], ['name' => 'Acme']);

        if ($acme->wasRecentlyCreated) {
            $acme->changePlan('pro');

            Teams::addMember($acme, $owner, Teams::ownerRole());
            Teams::addMember($acme, $member, 'member');
            Teams::invite($acme, 'nina@example.com', 'admin', $owner, send: false);

            foreach (['Website relaunch' => ProjectStatus::Active, 'Mobile app' => ProjectStatus::Active, 'Brand refresh' => ProjectStatus::Paused] as $name => $status) {
                Project::factory()->for($acme)->create(['name' => $name, 'status' => $status]);
            }
        }

        $this->command?->table(['Sign in at', 'E-mail', 'Password', 'Who'], [
            ['/app', self::OWNER_EMAIL, self::PASSWORD, 'Owner of Acme (Pro plan)'],
            ['/app', self::MEMBER_EMAIL, self::PASSWORD, 'Member of Acme'],
            ['/admin', self::ADMIN_EMAIL, self::PASSWORD, 'Admin: users, workspaces, support logins'],
        ]);
    }

    protected function user(string $email, string $name, bool $admin = false): User
    {
        $user = User::firstOrNew(['email' => $email]);

        if (! $user->exists) {
            $user->forceFill([
                'name' => $name,
                'password' => self::PASSWORD,
                'email_verified_at' => now(),
                'is_admin' => $admin,
            ])->save();
        }

        return $user;
    }
}
