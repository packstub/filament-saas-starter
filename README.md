# Filament SaaS Starter

A starting point for a multi-tenant SaaS on Laravel 13 and Filament 5: people sign up, get a workspace, invite their team, and pay for a plan that sets their limits. It is a regular Laravel app that you own and change from the first commit.

Free and open source, under the MIT license.

## What you get in the first hour

- **[Sign-up and log-in](#accounts-and-security)**: Filament's own auth pages, with e-mail verification, password reset, a profile page and two-factor authentication.
- **[Workspaces](#workspaces)**: every sign-up gets one, a person can belong to several, and everything in the panel is scoped to the current one.
- **[Team members](#members-and-invitations)**: invitations by e-mail or link, the roles Owner, Admin and Member, and seats counted against the plan.
- **[Plans and limits](#plans-and-limits)**: Free, Pro and Business in one config file, a **Your plan** page with usage, and overrides for a single workspace.
- **[Billing](#billing-with-stripe)**: Stripe Checkout and the customer portal through Laravel Cashier, monthly and yearly prices and a free trial, with a webhook that puts the workspace on the plan it pays for.
- **[Support logins](#support-logins)**: an admin panel where you sign in as a customer to see what they see, with a log of every switch.
- **[One complete example](#the-projects-pattern)**: Projects, scoped to the workspace, guarded by a policy and counted against a plan limit.
- **[Tests](#tests)**: a Pest suite over all of the above, and GitHub Actions to run it.

It is built from Packstub's free plugins [Filament Teams](https://packstub.dev/docs/filament-teams), [Filament Features](https://packstub.dev/docs/filament-features) and [Filament Account Switcher](https://packstub.dev/docs/filament-account-switcher), together with [Laravel Cashier](https://laravel.com/docs/billing) and Filament's [built-in tenancy](https://filamentphp.com/docs/5.x/users/tenancy).

## Requirements

| | Version |
| --- | --- |
| PHP | 8.3 or newer |
| Laravel | 13 |
| Filament | 5 |
| Node | 20.19 or newer, to build the home page's CSS |

The database is SQLite out of the box. For MySQL or PostgreSQL, change `DB_CONNECTION` in `.env`.

`composer.json` pins `config.platform.php` to `8.3.0`, so the lock file installs on PHP 8.3 whatever version you develop on. While it is there, Composer resolves every package as if you ran PHP 8.3, so a package that needs PHP 8.4 will not install. Once you know where the app will run, raise it to your server's PHP version, or remove it:

```bash
composer config --unset platform.php
composer update --lock
```

## Install

With the [Laravel installer](https://laravel.com/docs/installation):

```bash
laravel new my-app --using=packstub/filament-saas-starter
```

That creates the project, migrates an SQLite database and seeds the demo accounts below.

Or click **Use this template** on GitHub (or clone the repository), then:

```bash
composer install
cp .env.example .env
php artisan key:generate
npm install && npm run build
php artisan migrate --seed
```

`composer setup` runs those steps in one go.

Serve it with [Laravel Herd](https://herd.laravel.com) or `composer dev`, and sign in:

| Sign in at | E-mail | Password | Who |
| --- | --- | --- | --- |
| `/app` | `demo@example.com` | `password` | Owner of the Acme workspace, on the Pro plan |
| `/app` | `member@example.com` | `password` | Member of Acme |
| `/admin` | `admin@example.com` | `password` | Admin: users, workspaces, support logins |

In the local environment the login pages also show the three accounts as one-click buttons.

Or open `/app/register` and sign up as a new customer. The verification e-mail goes to `storage/logs/laravel.log` (`MAIL_MAILER=log`), and its link opens the panel. `.env.example` sets `QUEUE_CONNECTION=sync`, so that e-mail is sent without a queue worker. In production, use a real queue.

When you are done with the demo, remove the three demo accounts: first take their e-mail addresses out of `app/Support/SupportLogins.php`, then empty `database/seeders/DatabaseSeeder.php`. The seeder skips the demo accounts when `APP_ENV` is `production`. The one-click logins exist only while `APP_ENV` is `local`, so never deploy with `APP_ENV` set to `local`.

## A tour

The app has two Filament panels:

- `/app/{workspace}` is what your customers use. It is defined in `app/Providers/Filament/AppPanelProvider.php`, and its pages and resources live in `app/Filament/App`.
- `/admin` is your back office. It is defined in `app/Providers/Filament/AdminPanelProvider.php`, with its resources in `app/Filament/Admin`.

The home page at `/` is `resources/views/welcome.blade.php`, a placeholder for your landing page.

### Workspaces

A workspace is the tenant: the `App\Models\Workspace` model, with its slug in the URL. All workspaces share one database, and Filament scopes every resource in the `app` panel to the current one.

- Signing up creates the person's first workspace, named after them. The closure that does it is `personalTeams()` in `AppPanelProvider`.
- The panel opens once the new account's e-mail address is verified (`emailVerification()` in `AppPanelProvider`, `MustVerifyEmail` on `User`). Invitations are matched by e-mail address, so an address has to be verified before it counts. A new address typed on the **Profile** page replaces the old one only after the link sent to it is followed (`emailChangeVerification()`).
- **New workspace** in the workspace menu is `app/Filament/App/Pages/Tenancy/RegisterWorkspace.php`. A person can create as many as they like, each starting on the Free plan. If your pricing needs a limit, add it there.
- `Workspace::named()` creates a workspace from a name: at most 60 characters, with a unique slug of at most 50, the suffix of a repeated name included.
- A name that matches a page of the panel gets a suffix too: "New" becomes `new-x7k2`, because `/app/new` is the **New workspace** page. `Workspace::reservedSlugs()` reads those words from the panel's routes, so a page or plugin you add later is covered.
- **Workspace settings** is `EditWorkspace.php` next to it, open to owners and admins through `app/Policies/WorkspacePolicy.php`.
- `User::getDefaultTenant()` picks the workspace a person lands in after signing in.

### Accounts and security

- **Passwords** follow `Password::defaults()` in `app/Providers/AppServiceProvider.php`, on the sign-up, password reset and profile pages: at least 12 characters and not found in known data leaks in production, at least 8 elsewhere so the demo accounts can keep `password`.
- **Two-factor authentication** is Filament's own, with an authenticator app and recovery codes. Customers switch it on from their **Profile** page. The admin panel requires it: an admin without it is sent to set it up before any page opens. In the local environment `/admin` skips that step, so the demo admin gets in with one click.
- The secret and the recovery codes are stored encrypted in two columns of the `users` table.
- **Strict models**: outside production, `Model::shouldBeStrict()` turns a lazy-loaded relation (an N+1 query), a missing attribute and a field left out of `Fillable` into an error, so you find them while developing.

### Members and invitations

The **Members** and **Invitations** pages come from Filament Teams. An owner or admin clicks **Invite member**, and the invited person gets an e-mail with a link to the sign-in page.

Someone with an account signs in there and joins. They also find the invitation under the bell icon. Someone new follows **sign up for an account**, finds the invited address filled in, and joins the workspace instead of getting one of their own.

- Roles and what each may do are in `config/packstub-teams.php`: **Owner** can do everything, **Admin** manages members, settings and billing, **Member** works with the workspace's data.
- Check a role anywhere with `$user->hasTeamPermission($workspace, 'delete')` or `$user->canManageTeam($workspace)`.
- Seats are members plus pending invitations, and their limit comes from the plan.
- Invitation e-mails go to the log locally (`MAIL_MAILER=log`): the link is in `storage/logs/laravel.log`. They are queued (`invitations.mail.queue` in `config/packstub-teams.php`), which with `QUEUE_CONNECTION=sync` still means sent right away.

Read more in the [Filament Teams docs](https://packstub.dev/docs/filament-teams).

### Plans and limits

Plans and the features they include are defined once, in `config/packstub-features.php`:

| Plan | Projects | Seats | Priority support |
| --- | --- | --- | --- |
| Free | 3 | 2 | |
| Pro | 50 | 10 | |
| Business | Unlimited | Unlimited | Included |

A workspace's plan is its `plan` column. Filament Features reads it and answers the questions your code asks:

```php
use Packstub\Features\Facades\Features;

Features::limit('projects');             // 50, or null for unlimited
Features::canUse('projects');            // is there room for one more?
Features::active('priority_support');    // is the feature included?
```

- `app/Support/Plans.php` tells Features how much of each limit a workspace has used, and points **Upgrade** to the Billing page.
- **Your plan** in the `app` panel shows the workspace its limits and usage.
- **Feature flags** and **Feature overrides** in `/admin` turn a feature off everywhere or give one workspace more than its plan, with an optional end date.
- **Change plan** on a workspace in `/admin` puts it on a plan without a subscription.

To add a limit, define the feature in the config file, give each plan a value, and add a `usage()` line in `Plans.php`. Read more in the [Filament Features docs](https://packstub.dev/docs/filament-features).

### Billing with Stripe

The **Billing** page (`app/Filament/App/Pages/Billing.php`) shows the plans to a workspace's owners and admins. The workspace is the Stripe customer, so one subscription covers all its members.

The app runs without a Stripe account: the Billing page then lists the `.env` keys that are still missing, and every workspace keeps its plan. To turn billing on:

1. In the Stripe dashboard, in test mode, create a product for Pro and one for Business, each with a monthly recurring price and, if you sell it yearly, a yearly one.
2. Fill in `.env`:

   ```ini
   STRIPE_KEY=pk_test_...
   STRIPE_SECRET=sk_test_...
   STRIPE_PRICE_PRO_MONTHLY=price_...
   STRIPE_PRICE_PRO_YEARLY=price_...
   STRIPE_PRICE_BUSINESS_MONTHLY=price_...
   STRIPE_PRICE_BUSINESS_YEARLY=price_...
   ```

   Leave a yearly price empty to sell that plan monthly only.

3. Forward Stripe's webhooks to your machine with the [Stripe CLI](https://docs.stripe.com/stripe-cli), and copy the signing secret it prints into `STRIPE_WEBHOOK_SECRET`:

   ```bash
   stripe listen --forward-to https://my-app.test/stripe/webhook
   ```

4. Turn on the [customer portal](https://dashboard.stripe.com/test/settings/billing/portal) in Stripe, so customers can update their card and cancel.

In production, `php artisan cashier:webhook` creates the webhook endpoint in Stripe; its signing secret goes into `STRIPE_WEBHOOK_SECRET`.

How a payment becomes a plan:

- A plan's button opens Stripe Checkout for the price in `config/billing.php`. A workspace that already pays gets **Switch to** instead, which moves its subscription to the other price.
- A workspace's first subscription starts with a free trial: 14 days, set by `BILLING_TRIAL_DAYS` (`trial_days` in `config/billing.php`, 0 turns it off). Checkout still takes the card, so the plan carries on when the trial ends; the Billing page and the dashboard say when that is. A workspace that has had a subscription before pays from the first day. If the trial ends without a payment, the webhook puts the workspace back on the Free plan.
- **Monthly** and **Yearly** above the plans switch the prices the cards show, once any plan has a yearly price. Both prices of a plan map back to it, so a yearly subscriber gets the same limits as a monthly one, and a monthly subscriber can move to yearly billing on the same plan.
- Stripe calls `/stripe/webhook`. Cashier stores the subscription, then `app/Listeners/SyncWorkspacePlanFromStripe.php` puts the workspace on the plan of that price.
- When the subscription ends, the same listener puts the workspace back on the Free plan. Its members and projects stay, but inviting and creating stop at the Free limits. The same happens when you delete the customer in Stripe.
- While Stripe retries a failed payment (`past_due`), the workspace keeps its plan. The Billing page then shows "Your last payment did not go through" with **Manage billing**. It does not offer a new checkout while that subscription is still running.
- A switch that needs the card confirmed (3D Secure) continues on Cashier's payment page. So does a first payment left unfinished at Checkout: the Billing page shows "Your payment is not finished yet" with **Complete payment**.
- **Manage billing** opens Stripe's customer portal.
- Deleting a `Workspace` cancels its Stripe subscription first (the `deleting` hook on the model), so nothing keeps billing, then removes its projects, subscription rows and feature overrides. The kit has no button that deletes a workspace; add one where it suits your product.
- A subscription on a price that `config/billing.php` does not list (a price added in the Stripe dashboard, say) leaves the plan as it is and writes a warning to the log.

In production, set `STRIPE_WEBHOOK_SECRET` before taking payments. Cashier verifies a webhook's signature only when the secret is set. Until it is, the app refuses Stripe's webhooks with a 403 and an error in the log (`app/Listeners/RefuseUnsignedStripeWebhooks.php`), and the Billing page sells nothing.

The amounts people see (`$29/mo`, `$290/yr`) are text next to each price in `config/billing.php`. Keep them in step with your Stripe prices. Read more in the [Laravel Cashier docs](https://laravel.com/docs/billing).

### Support logins

In `/admin`, **Users** lists everyone with an account. **Impersonate** signs you in as that person and takes you to their workspace, under a banner that says who you are signed in as. **Switch back** returns you to the admin panel.

- Only users with `is_admin` can open `/admin` (`User::canAccessPanel()`), and only with [two-factor authentication](#accounts-and-security) set up.
- Only admins can sign in as someone else, and never as another admin (`User::canImpersonate()` and `User::canBeImpersonated()`).
- Every switch is written to the `account_switches` table.
- The plugin is set up once for both panels in `app/Support/SupportLogins.php`.

Make your own account an admin from `php artisan tinker`:

```php
App\Models\User::where('email', 'you@example.com')->update(['is_admin' => true]);
```

Read more in the [Filament Account Switcher docs](https://packstub.dev/docs/filament-account-switcher).

### The Projects pattern

Projects are the example to copy for your own models. Six files make a resource belong to a workspace and count against a plan:

- `database/migrations/workspace/` holds the migrations of the tables a workspace owns. A table there has a ULID key and an indexed `workspace_id` without a database foreign key; the folder's README says why.
- `app/Models/Project.php` has a `workspace()` relationship. Filament uses it to scope every query in the panel and to fill in the workspace on create.
- `app/Enums/ProjectStatus.php` is the `status` column as a backed enum. It carries its own label and colour (`HasLabel`, `HasColor`), so the select in the form and the badge in the table need no extra code.
- `app/Policies/ProjectPolicy.php` decides who may do what from the member's role, and allows creating only while the plan has room. It asks `User::canInWorkspace()`, which looks the role up once per request however many rows the table shows, and again as soon as a membership or a role changes.
- `app/Filament/App/Resources/Projects/Pages/ManageProjects.php` shows "3 of 50 projects on the Pro plan", and swaps **New project** for **Upgrade for more projects** at the limit. It checks the limit once more while saving, under a lock per workspace.
- `app/Support/Plans.php` counts the workspace's projects for the `projects` limit.

For a new model:

1. Put its migration in `database/migrations/workspace`, with a ULID key and an indexed `workspace_id`.
2. Add `HasUlids` and the `workspace()` relationship to the model.
3. Delete its rows in `Workspace`'s `deleting` hook.
4. Write the policy.
5. Create the resource in `app/Filament/App/Resources`.
6. Add a limit if the plan should cap it.

### Tests

Run them with:

```bash
composer test
```

This checks the code style with Pint and runs the Pest suite. In `tests/Feature`:

- `HomePageTest`: the home page links to sign-up and log-in.
- `SignUpTest`: signing up creates a workspace with the person as owner, the panel opens after e-mail verification, long names, a second workspace, guests and other people's workspaces stay closed.
- `AuthenticationTest`: the password rules, signing in with and without an authenticator app, two-factor authentication required for the admin panel.
- `WorkspaceTest`: reserved slugs, workspace settings by role, what deleting a workspace removes.
- `InvitationTest`: inviting from the Members page, signing up through an invitation, accepting with an existing account, an unverified account that claims an invited address, expired invitations, the seat limit.
- `ProjectLimitTest`: projects stay in their workspace, creating stops at the plan's limit and resumes on a bigger plan, a smaller plan keeps what is there.
- `ProjectAccessTest`: what each role may do with a project, other workspaces' projects stay out of reach by URL and by action, and the list runs the same number of queries however long it is.
- `BillingTest`: subscription webhooks (created, updated, each Stripe status, a trial and its end, deleted, customer deleted) change the plan and the limits; signed and unsigned webhooks; unknown prices.
- `BillingPageTest`: the Billing page by role, with and without Stripe keys, monthly and yearly prices, the free trial, Checkout, switching plans and intervals, the customer portal, a past-due subscription and a payment that needs confirming.
- `SupportLoginTest`: the admin panel is for admins, signing in as a customer and switching back, changing a plan by hand, the admin tables.
- `SeederTest` and `ScheduleTest`: the demo data seeds once and never in production; the daily housekeeping is scheduled.

`tests/Unit/ArchTest.php` holds the architecture tests: Pest's `php`, `security` and `laravel` presets and a few rules of the kit's own.

The webhook tests post Stripe's payloads to the app, and `fakeStripe()` in `tests/Pest.php` answers the calls the app makes to Stripe, so the suite needs no Stripe account. `.github/workflows/tests.yml` runs it on PHP 8.3, 8.4 and 8.5.

## Going further

### A database per workspace

One shared database is the simplest setup and the right one for most products. When a customer needs their data kept apart, or one database stops being enough, [Filament Tenancy](https://packstub.dev/plugins/filament-tenancy) (paid) adopts the app as it is and lets you move a workspace to a shard or to a database of its own.

The kit is laid out for that move: what a workspace owns sits in `database/migrations/workspace`, with ULID keys and no foreign keys to the tables that stay central (`users`, `workspaces`, memberships, invitations, billing).

The steps, in the order you meet them:

1. **PHP 8.4.** Filament Tenancy needs PHP 8.4 or newer. The kit itself runs on 8.3.
2. **Adopt.** After `composer require packstub/filament-tenancy`, run `php artisan tenancy:adopt "App\Models\Workspace"`. Every workspace becomes a tenant with the same table, ids and `workspace_user` pivot. Adopting keeps all workspaces in the shared database; nothing moves yet.
3. **The `Workspace` model.** Both Filament Tenancy's `IsPackstubTenant` and Filament Teams' `HasTeamMembers` define `isOwnedBy()`, so the command prints the new class declaration for you to paste. Add one line to it:

   ```php
   use IsPackstubTenant { IsPackstubTenant::isOwnedBy insteadof HasTeamMembers; }
   ```

4. **The `User` model.** It gets `HasPackstubTenants`, which brings its own `getTenants()`, `canAccessTenant()` and `getDefaultTenant()`. Swap `HasTeamMemberships` for `HasTeamRoles`, remove the kit's `getDefaultTenant()`, and add stancl/tenancy's `CentralConnection` trait, so accounts are always read from the central database. The app's own code asks the `Teams` facade (`Teams::ownersOf()`, `Teams::membersOf()`, `Teams::teamsOf()`) instead of the traits' relations, so it keeps working. Two places name the `joinedTeams()` relation: the tests, which can use `Teams::teamsOf()`, and the workspace count of the admin **Users** table (`withCount('joinedTeams')` in `UserResource`), which becomes `withCount('tenants')`.
5. **Paths or subdomains.** The kit serves workspaces under `/app/{workspace}`. To keep that, set `'identification' => 'path'` and `'route_prefix' => null` in `config/packstub-tenancy.php` before you run `php artisan migrate`. Without the second line, URLs become `/app/teams/{workspace}`. The default is a subdomain per workspace, which needs wildcard DNS and `$workspace->ensureDefaultDomain()` after `Workspace::named()`.
6. **The panel.** `->tenant()`, `->tenantRegistration()` and `->tenantProfile()` in `AppPanelProvider` make way for `TenancyPlugin::make()`, which registers its own onboarding and profile pages.
7. **Moving a workspace.** With a shard connection added (`shard_2` here) and the migrations in `database/migrations/workspace` run on it, check with `php artisan tenants:move acme --to=shard_2 --dry-run --exclude=subscriptions`, then run it without `--dry-run`. Always pass `--exclude=subscriptions`: Cashier's `subscriptions` table carries `workspace_id` but belongs with billing, on the central database where the webhooks write.
8. **Tables that stay central.** Inside a moved workspace the default connection is the workspace's database, so everything central has to name its connection. The kit already does: `config/packstub-teams.php` and `config/packstub-features.php` set `connection` to `DB_CONNECTION`, which is the connection Filament Tenancy treats as central. Filament Account Switcher's tables, notifications and Cashier's subscriptions follow the `User` and `Workspace` models, which are central. Give a central model of your own the `CentralConnection` trait too.
9. **Code outside a workspace's request.** The admin panel, jobs and commands run on the central database. To read what a workspace owns there, run inside it: `$workspace->run(fn () => Project::count())`. The usage counter in `app/Support/Plans.php` and the `deleting` hook on `Workspace` are the two places in the kit that read projects.

Read [Adopting an existing app](https://packstub.dev/docs/filament-tenancy/adopting-an-existing-app) and [Database strategies](https://packstub.dev/docs/filament-tenancy/database-strategies) before you start. The command prints every step it leaves to you.

### Built and launched for you

[XLITE](https://xlite.dev/services/saas-mvp/) builds and launches your SaaS for you, as the SaaS MVP package.

## Security

If you discover a security issue, please e-mail [security@packstub.dev](mailto:security@packstub.dev) instead of using the issue tracker.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
