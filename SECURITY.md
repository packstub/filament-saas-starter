# Security policy

Filament SaaS Starter is a starter kit: a project created from it is your own app from the first commit. This policy covers the kit itself, the code a new project starts with. Please report anything in that area privately so a fix can ship before details become public.

## Supported versions

Security fixes are released for the latest minor version of each supported release line. A project already created from the kit does not update itself: the release notes say what to change in your copy.

| Version | Supported |
| ------- | --------- |
| 1.x     | Yes       |

## Reporting a vulnerability

Email **security@packstub.dev**. Do not open a public GitHub issue, discussion, or pull request for a security problem.

Include what you can: the kit version or commit your project started from, the PHP, Laravel and Filament versions, the database driver, and a minimal reproduction.

What to expect: acknowledgment within 48 hours from a human, updates while we validate and fix, and coordinated disclosure with credit in the release notes unless you prefer otherwise.

## Scope

Highest priority:

- **Workspace isolation**: reading or changing a project, member, invitation, plan or subscription of a workspace the signed-in user does not belong to.
- **Roles**: a member doing what the kit keeps for owners and admins (workspace settings, billing, invitations, deleting projects).
- **The admin panel and support logins**: reaching `/admin` without `is_admin` and two-factor authentication, signing in as someone else without being an admin, or as another admin.
- **Billing**: a workspace reaching a paid plan without paying for it, including through the Stripe webhook.
- **Sign-up and invitations**: joining a workspace through an invitation sent to an e-mail address the account has not verified.

Out of scope: vulnerabilities in Laravel, Filament, Livewire, Laravel Cashier or the Packstub plugins themselves (report those to their own projects; for [Filament Teams](https://github.com/packstub/filament-teams), [Filament Features](https://github.com/packstub/filament-features) and [Filament Account Switcher](https://github.com/packstub/filament-account-switcher) the address is the same), issues that need an already-compromised admin account or server, and changes made to a project after it was created.

## Before you deploy a project made from the kit

- Remove the demo accounts: take their e-mail addresses out of `app/Support/SupportLogins.php` and empty `database/seeders/DatabaseSeeder.php`. The seeder already refuses to run in production.
- Never deploy with `APP_ENV=local`: the one-click developer logins exist in that environment only, and the admin panel asks for two-factor authentication everywhere else.
- Set `STRIPE_WEBHOOK_SECRET` before taking payments. Until it is set, the app refuses Stripe's webhooks in production.
