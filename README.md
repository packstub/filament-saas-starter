# Filament SaaS Starter

A starting point for a multi-tenant SaaS on Laravel 13 and Filament 5: people sign up, get a workspace, invite their team, and pay for a plan that sets their limits. It is a regular Laravel app that you own and change from the first commit.

Free and open source, under the MIT license.

## What you get in the first hour

- **[Sign-up and log-in](#accounts-and-security)**: Filament's own auth pages, with e-mail verification, Google and GitHub sign-in, password reset, a profile page, two-factor authentication, a download of your data and account deletion.
- **[Workspaces](#workspaces)**: every sign-up gets one, with a Get started checklist on its dashboard; a person can belong to several, and everything in the panel is scoped to the current one.
- **[Team members](#members-and-invitations)**: invitations by e-mail or link, the roles Owner, Admin and Member, and seats counted against the plan.
- **[Plans and limits](#plans-and-limits)**: Free, Pro and Business in one config file, a **Your plan** page with usage, and overrides for a single workspace.
- **[Billing](#billing-with-stripe)**: Stripe Checkout and the customer portal through Laravel Cashier, monthly and yearly prices, a free trial and optional per-seat pricing, with a webhook that puts the workspace on the plan it pays for.
- **[An API](#api-tokens-and-the-api)**: API tokens per workspace with abilities and expiry, and a versioned REST API for projects that respects the plan's limits.
- **[Audit log](#audit-log)**: who joined, left, changed role, was invited or took over a workspace, and every plan change, on an Activity page for owners and admins.
- **[Revenue metrics](#revenue-metrics)**: MRR, paying subscriptions, trials, churn, sign-ups and plans on the admin dashboard, read from your own database.
- **[Support logins](#support-logins)**: an admin panel where you sign in as a customer to see what they see, with a log of every switch and every workspace's activity.
- **[One complete example](#the-projects-pattern)**: Projects, scoped to the workspace, guarded by a policy and counted against a plan limit.
- **[A home page](#the-home-page)**: a landing page with features, pricing read from your plans, a FAQ, Terms and Privacy placeholders, and the meta tags, sitemap and robots.txt search engines look for.
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

The public site around them (the home page, Terms, Privacy) is described in [The home page](#the-home-page).

### The home page

`/` is a landing page for the example product: a hero, a feature list, pricing, a FAQ and a call to action. It is plain Blade and Tailwind (`resources/views/site`, built by `npm run build`), follows the visitor's light or dark mode, and works without JavaScript. Rewrite the copy for your product; the parts below keep themselves up to date.

- **Pricing** is generated by `app/Support/Pricing.php` from the plans in `config/packstub-features.php` (names, descriptions, what each includes) and the amounts in `config/billing.php`. The Monthly / Yearly switch appears once a plan has a yearly price, and the free trial is mentioned while `BILLING_TRIAL_DAYS` is above 0. Once Stripe prices are set, a plan shows only the intervals it is sold at, as on the Billing page; before any is set, every amount shows, so a fresh copy looks complete. Every button leads to sign-up; people pick a paid plan from the Billing page.
- **`config/site.php`** holds the tagline, the meta description, the company name and contact address on the legal pages and in the footer, and an optional X/Twitter handle (`SITE_*` in `.env`).
- **Every URL search engines keep** (the canonical URL, `og:url`, the sitemap and robots.txt's `Sitemap:` line) is built on `APP_URL` by `app/Support/SiteUrl.php`, never on the host a request came in on, so set `APP_URL` to the public address, with `https://`. Behind a load balancer or a CDN that terminates TLS, also trust its proxy (`$middleware->trustProxies(...)` in `bootstrap/app.php`), and consider `$middleware->trustHosts()` to refuse other hosts altogether.
- **Meta tags** live in `resources/views/components/site/layout.blade.php`: title, description, canonical URL, Open Graph and Twitter cards. Put a 1200×630 `public/images/og.png` there and link previews show it.
- **`/sitemap.xml`** lists the public pages (the home page, and Terms and Privacy once they are your own) and **`/robots.txt`** keeps search engines out of `/admin` and the panel, apart from the log-in and sign-up pages. Outside production, robots.txt asks them to stay away altogether, so a staging copy is never indexed. Both are routes in `routes/web.php`; add a new page's route name to the sitemap there.
- **Terms** (`/terms`) and **Privacy** (`/privacy`) are placeholders with a "Replace with your own" notice and a `noindex` tag, and stay out of the sitemap. Write your own (they are needed before Stripe takes payments), then set `SITE_LEGAL_PLACEHOLDERS=false` (`legal_placeholders` in `config/site.php`): the notice and `noindex` go, and both pages join the sitemap.

### Workspaces

A workspace is the tenant: the `App\Models\Workspace` model, with its slug in the URL. All workspaces share one database, and Filament scopes every resource in the `app` panel to the current one.

- Signing up creates the person's first workspace, named after them. The closure that does it is `personalTeams()` in `AppPanelProvider`.
- The panel opens once the new account's e-mail address is verified (`emailVerification()` in `AppPanelProvider`, `MustVerifyEmail` on `User`). Invitations are matched by e-mail address, so an address has to be verified before it counts. A new address typed on the **Profile** page replaces the old one only after the link sent to it is followed (`emailChangeVerification()`).
- **New workspace** in the workspace menu is `app/Filament/App/Pages/Tenancy/RegisterWorkspace.php`. A person can create as many as they like, each starting on the Free plan. If your pricing needs a limit, add it there.
- `Workspace::named()` creates a workspace from a name: at most 60 characters, with a unique slug of at most 50, the suffix of a repeated name included.
- A name that matches a page of the panel gets a suffix too: "New" becomes `new-x7k2`, because `/app/new` is the **New workspace** page. `Workspace::reservedSlugs()` reads those words from the panel's routes, so a page or plugin you add later is covered.
- **Workspace settings** is `EditWorkspace.php` next to it, open to owners and admins through `app/Policies/WorkspacePolicy.php`.
- **Delete workspace**, at the bottom of Workspace settings, is for owners only (`WorkspacePolicy::delete()`), never during a support login, and asks for the workspace's name. It runs `$workspace->delete()`: the Stripe subscription is cancelled first, then the projects, members, invitations, subscription rows and overrides go in one transaction. If Stripe refuses, nothing is deleted and the owner is told. Afterwards the panel opens another of their workspaces, or the **New workspace** page.
- **Get started**, on the dashboard of a new workspace (`app/Filament/App/Widgets/GettingStarted.php`), shows owners and admins four steps: invite a teammate, create a first project, choose a plan, turn on two-factor authentication. Each one is worked out from what is really there (members and invitations, projects, a subscription or a paid plan, the person's authenticator app), so it ticks itself off. The checklist goes once every step is done, or when someone clicks **Dismiss**, which hides it for the whole workspace (`workspaces.onboarding_dismissed_at`). Change the steps in `GettingStarted::steps()`.
- `User::getDefaultTenant()` picks the workspace a person lands in after signing in.

### Accounts and security

- **Passwords** follow `Password::defaults()` in `app/Providers/AppServiceProvider.php`, on the sign-up, password reset and profile pages: at least 12 characters and not found in known data leaks in production, at least 8 elsewhere so the demo accounts can keep `password`.
- **Two-factor authentication** is Filament's own, with an authenticator app and recovery codes. Customers switch it on from their **Profile** page. The admin panel requires it: an admin without it is sent to set it up before any page opens. In the local environment `/admin` skips that step, so the demo admin gets in with one click.
- The secret and the recovery codes are stored encrypted in two columns of the `users` table.
- **Download your data**, on the **Profile** page, gives a person one JSON file with everything the app holds about them: their account, the workspaces they are in and their role, their notifications, and every support login into their account. **Export workspace data**, in Workspace settings, does the same for a workspace, for owners only (`WorkspacePolicy::export()`): members, invitations, subscriptions, overrides and projects. Both come from `app/Support/DataExport.php` and are plain GET downloads (`/exports/me`, `/exports/workspaces/{slug}`, in `app/Http/Controllers`) streamed to the browser as they are read, so a workspace with many projects never sits in memory whole. The buttons link there rather than returning the file from a Filament action, which Livewire would buffer whole. A table you add for a workspace belongs in `DataExport::forWorkspace()` too.
- **Delete account** is at the bottom of the **Profile** page (`app/Filament/App/Pages/Auth/EditProfile.php`), behind the person's password. `User::deleteAccount()` deletes every workspace where they are the only member (cancelling its subscription first) and takes them out of the others. Someone who is the only owner of a workspace others still use is asked to hand it over under **Members** (**Transfer ownership**) or delete it first, so no workspace is left without an owner. The button is hidden during a support login. Filament Account Switcher's own log loses the support logins into a deleted account (its `account_switches` rows go with the user); the workspaces' audit logs keep what support did, under "Deleted user".
- **Google and GitHub sign-in** ([Laravel Socialite](https://laravel.com/docs/socialite)) turn on once their keys are in `.env`; until then no button shows. See [Signing in with Google and GitHub](#signing-in-with-google-and-github).
- **Strict models**: outside production, `Model::shouldBeStrict()` turns a lazy-loaded relation (an N+1 query), a missing attribute and a field left out of `Fillable` into an error, so you find them while developing.

### Signing in with Google and GitHub

Create an OAuth app with [Google](https://console.cloud.google.com/apis/credentials) and/or [GitHub](https://github.com/settings/developers), register the callback URL (`https://my-app.test/auth/google/callback`, `https://my-app.test/auth/github/callback`), and fill in `.env`:

```ini
GOOGLE_CLIENT_ID=...
GOOGLE_CLIENT_SECRET=...
GITHUB_CLIENT_ID=...
GITHUB_CLIENT_SECRET=...
```

A provider with both values set gets a **Continue with …** button under the sign-in and sign-up forms, and a **Connect** button in **Connected accounts** on the Profile page. The routes are in `routes/web.php`, the flow in `app/Http/Controllers/Auth/SocialCallbackController.php`, the enabled providers in `app/Support/SocialLogin.php`.

- An account already connected signs its user in. A new one whose e-mail address the provider has verified is connected to the user with that address, or creates a user (already verified, with their first workspace, or the workspace of the invitation that brought them).
- An address is linked only when both sides have verified it. If the provider has not verified it, or the account with that address has not been verified yet, nothing is linked and the person is asked to sign in with their password: otherwise whoever registered an address they do not own could take over the other account.
- Two-factor authentication is never skipped: someone with an authenticator app, and every admin, signs in with their password and code instead.
- Never during a [support login](#support-logins): both routes refuse while an admin is signed in as someone, so support can never leave a Google or GitHub account of their own on a customer's account.
- Someone who signed up this way has no password (`users.password` is nullable) until they choose one on the Profile page, which then does not ask for a current one. **Disconnect** is refused for their last way to sign in, and **Delete account** asks them to type their e-mail address instead of a password.
- Connected accounts are in `social_accounts`, a central table removed with the user, and in the personal data export.

### Members and invitations

The **Members** and **Invitations** pages come from Filament Teams. An owner or admin clicks **Invite member**, and the invited person gets an e-mail with a link to the sign-in page.

Someone with an account signs in there and joins. They also find the invitation under the bell icon. Someone new follows **sign up for an account**, finds the invited address filled in, and joins the workspace instead of getting one of their own.

- Roles and what each may do are in `config/packstub-teams.php`: **Owner** can do everything, **Admin** manages members, settings and billing, **Member** works with the workspace's data.
- **Transfer ownership**, on a member's row, is for owners: the member becomes the owner and the former owner stays on as an admin. Only an owner can delete the workspace or export its data, and Stripe's receipts go to the owner's address, so `app/Listeners/UpdateStripeBillingContact.php` queues `app/Jobs/SyncStripeCustomer.php` to update the Stripe customer after a transfer (by the workspace's id, like the seat sync).
- Check a role anywhere with `$user->hasTeamPermission($workspace, 'delete')` or `$user->canManageTeam($workspace)`.
- Seats are members plus pending invitations, and their limit comes from the plan.
- Invitation e-mails go to the log locally (`MAIL_MAILER=log`): the link is in `storage/logs/laravel.log`. They are queued (`invitations.mail.queue` in `config/packstub-teams.php`), which with `QUEUE_CONNECTION=sync` still means sent right away.

Read more in the [Filament Teams docs](https://packstub.dev/docs/filament-teams).

### Plans and limits

Plans and the features they include are defined once, in `config/packstub-features.php`:

| Plan | Projects | Seats | API access | Priority support |
| --- | --- | --- | --- | --- |
| Free | 3 | 2 | | |
| Pro | 50 | 10 | Included | |
| Business | Unlimited | Unlimited | Included | Included |

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

   Leave a yearly price empty to sell that plan monthly only. In a copy made before yearly prices the keys were `STRIPE_PRICE_PRO` and `STRIPE_PRICE_BUSINESS`; they are still read as the monthly prices, but rename them.

3. Forward Stripe's webhooks to your machine with the [Stripe CLI](https://docs.stripe.com/stripe-cli), and copy the signing secret it prints into `STRIPE_WEBHOOK_SECRET`:

   ```bash
   stripe listen --forward-to https://my-app.test/stripe/webhook
   ```

4. Turn on the [customer portal](https://dashboard.stripe.com/test/settings/billing/portal) in Stripe, so customers can update their card and cancel.

In production, `php artisan cashier:webhook` creates the webhook endpoint in Stripe; its signing secret goes into `STRIPE_WEBHOOK_SECRET`.

How a payment becomes a plan:

- A plan's button opens Stripe Checkout for the price in `config/billing.php`. A workspace that already pays gets **Switch to** instead, which moves its subscription to the other price.
- A workspace's first subscription starts with a free trial: 14 days, set by `BILLING_TRIAL_DAYS` (`trial_days` in `config/billing.php`, 0 turns it off). Checkout still takes the card, so the plan carries on when the trial ends; the Billing page and the dashboard say when that is. A workspace that has had a subscription before pays from the first day. The trial is per workspace, so a new workspace gets one too; `config/billing.php` says how to make it one per person if that matters to you. If the trial ends without a payment, the webhook puts the workspace back on the Free plan. Should Stripe pause the subscription instead (a trial whose card was removed), the Billing page asks for a card under **Manage billing** and offers **Resume subscription**, which bills from that day.
- **Monthly** and **Yearly** above the plans switch the prices the cards show, once any plan has a yearly price. Both prices of a plan map back to it, so a yearly subscriber gets the same limits as a monthly one, and a monthly subscriber can move to yearly billing on the same plan.
- Stripe calls `/stripe/webhook`. Cashier stores the subscription, then `app/Listeners/SyncWorkspacePlanFromStripe.php` puts the workspace on the plan of that price.
- When the subscription ends, the same listener puts the workspace back on the Free plan. Its members and projects stay, but inviting and creating stop at the Free limits. The same happens when you delete the customer in Stripe.
- While Stripe retries a failed payment (`past_due`), the workspace keeps its plan. The Billing page then shows "Your last payment did not go through" with **Manage billing**. It does not offer a new checkout while that subscription is still running.
- A switch that needs the card confirmed (3D Secure) continues on Cashier's payment page. So does a first payment left unfinished at Checkout: the Billing page shows "Your payment is not finished yet" with **Complete payment**.
- **Manage billing** opens Stripe's customer portal.
- Deleting a `Workspace` cancels its Stripe subscription first (the `deleting` hook on the model), so nothing keeps billing, then removes its projects, subscription rows and feature overrides. Owners do it from [Workspace settings](#workspaces).
- A subscription on a price that `config/billing.php` does not list (a price added in the Stripe dashboard, say) leaves the plan as it is and writes a warning to the log.

#### Per-seat pricing

Plans are flat by default: one price, whatever the number of members. To bill a plan per member instead, set `'per_seat' => true` on it in `config/billing.php`, give it per-unit prices in Stripe ("$9 per seat"), and say so in its `amount`. Then:

- Checkout starts the subscription with one seat per member. Pending invitations hold a seat against the plan's limit but are billed only once accepted.
- When someone joins (an accepted invitation included) or leaves, `app/Listeners/SyncSeatQuantityWithStripe.php` queues `app/Jobs/SyncSeatQuantity.php`, which sets the subscription's quantity to the number of members, and Stripe prorates the difference. The job runs once the membership is saved, so run a queue worker in production; a call Stripe refuses is reported and tried again. It carries the workspace's id only: Filament Teams' events hold whole `User` models, and a queued listener would write their password hashes and remember tokens into the `jobs` table.
- Every subscription webhook queues the same job, so a change made in the Stripe dashboard or a failed update is put right; a Stripe error there never fails the webhook.
- Switching between a flat and a per-seat plan sets the quantity to match.
- Give a per-seat plan unlimited `seats` in `config/packstub-features.php` (or a cap you want), since every seat is paid for.

In production, set `STRIPE_WEBHOOK_SECRET` before taking payments. Cashier verifies a webhook's signature only when the secret is set. Until it is, the app refuses Stripe's webhooks with a 403 and an error in the log (`app/Listeners/RefuseUnsignedStripeWebhooks.php`), and the Billing page sells nothing.

The amounts people see (`$29/mo`, `$290/yr`) are text next to each price in `config/billing.php`. Keep them in step with your Stripe prices. Read more in the [Laravel Cashier docs](https://laravel.com/docs/billing).

### API tokens and the API

**API tokens**, in the `app` panel, lets owners and admins create tokens for their scripts and integrations: a name, what the token may do (read projects, create projects) and when it expires (30 days, 90 days, a year or never). The token is shown once, right after it is created; only its hash is stored. **Revoke** stops it at once. Creating and revoking are in the [audit log](#audit-log). During a [support login](#support-logins) tokens can be revoked but not created, so support never leaves with access of its own.

- Tokens are [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens whose owner is the **workspace**, not a person (`HasApiTokens` on `Workspace`, `App\Models\ApiToken`). A token keeps working after the person who created it leaves, and the table shows who that was; if they delete their account, the token stays and their name goes. Deleting a workspace deletes its tokens.
- The API lives in `routes/api.php` under `/api/v1`, with `app/Http/Controllers/Api/V1/ProjectController.php` as the example: list (`GET /api/v1/projects`, paginated), show (`GET /api/v1/projects/{id}`) and create (`POST /api/v1/projects`). A request authenticates as the workspace (`$request->user()` is the `Workspace`), so every query starts from it and another workspace's records are out of reach.
- Each route names the ability it needs (`abilities:projects:read`); add yours to `ApiToken::ABILITIES` and they appear on the page.
- The API is a plan feature, `api_access` in `config/packstub-features.php` (Pro and Business). On a plan without it the page offers an upgrade, and the API answers 403, also for tokens created before a downgrade (`app/Http/Middleware/EnsureWorkspaceHasApiAccess.php`).
- Creating a project goes through `App\Actions\CreateProject`, so the plan's `projects` limit holds: the API answers 403 with the limit and an `upgrade_url`.
- Each token may make 60 requests a minute (the `api` rate limiter in `AppServiceProvider`). Errors are JSON, whatever the client asks for.
- The API answers to tokens only, never to a signed-in browser session (`guard` is empty in `config/sanctum.php`).
- Expired tokens are deleted a day after they expire (`sanctum:prune-expired`, scheduled daily).

```bash
curl https://my-app.test/api/v1/projects -H "Authorization: Bearer 1|..."
curl https://my-app.test/api/v1/projects -H "Authorization: Bearer 1|..." -d name="Website relaunch"
```

The `personal_access_tokens` table is central, like billing: a request names no workspace until its token has been read.

### Audit log

Every workspace keeps a log of what happened in it: members who joined, left or changed role, invitations sent and accepted, ownership transfers, plan changes (from Stripe, from the admin panel or from the code), API tokens created and revoked, and the workspace's deletion. **Activity**, in the `app` panel, shows it to owners and admins, with a filter by kind. In `/admin`, **Activity** lists every workspace's entries, deleted workspaces included, with filters by kind, by workspace and for what happened during a support login; **Activity** on a row of **Workspaces** opens that workspace's entries.

- `app/Models/AuditEvent.php` is an entry, and `AuditEvent::record($workspace, AuditEventType::…, [...])` writes one. Its kinds, with their labels, colours and icons, are `app/Enums/AuditEventType.php`.
- `app/Listeners/RecordWorkspaceActivity.php` turns Filament Teams' membership events into entries; `Workspace::changePlan()` and the model's `deleted` hook write the plan changes and the deletion.
- An entry names who did it, or "System" for a webhook or a command. During a [support login](#support-logins) it also names the admin behind it, so the customer's log shows "Ethan Park (support: Sam Carter)".
- People and workspaces are kept by id and by name as they were, with no foreign keys, so an entry still reads well after a workspace is deleted. When someone deletes their account, `AuditEvent::forget()` replaces their name and e-mail address with "Deleted user" in every entry; what happened stays. Their entries are part of **Download your data**. Deleting a workspace removes its entries and writes one "Workspace deleted" entry, which stays for the back office.
- The table is central, next to billing and the support-login log: the admin panel reads it across workspaces and it keeps the entry of a deleted workspace, so it stays on the main database if workspaces get databases of their own.
- Entries older than 365 days (`AUDIT_RETENTION_DAYS`, `config/audit.php`) are removed by `model:prune`, scheduled daily in `routes/console.php`. Entries hold names and e-mail addresses, so keep them no longer than your privacy policy says.
- A workspace's entries are part of its data export.

To log something of your own, add a case to `AuditEventType` with its sentence in `AuditEvent::description()`, and call `AuditEvent::record()` where it happens.

### Revenue metrics

The dashboard of `/admin` shows how the business is doing: monthly recurring revenue (and the year's worth), paying subscriptions, free trials, churn over the last 30 days, sign-ups and new workspaces per day, plan changes, MRR per plan and workspaces per plan. The widgets are in `app/Filament/Admin/Widgets`, the figures in `app/Billing/RevenueMetrics.php`.

- Everything is read from the app's own tables: Cashier's `subscriptions`, `users`, `workspaces` and the audit log. Nothing asks Stripe, so the dashboard opens fast and works before you have a Stripe account.
- A subscription's price becomes money through `unit_amount` in `config/billing.php` (cents, in `CASHIER_CURRENCY`), next to the `amount` people see. Keep both in step with Stripe. A yearly price counts as a twelfth a month, and a per-seat plan once per seat.
- MRR and **Paying subscriptions** count the same subscriptions: `active` or `past_due` (Stripe is still retrying the payment), on a price `config/billing.php` sells, including one cancelled at the end of its period until that period ends. Trials (Stripe's `trialing`) are listed on their own and are not revenue yet. The sums are made per price in the database, so the dashboard reads one row per price, not one per subscription.
- **Churned** is the subscriptions that ended in the last 30 days after paying at least once: a trial that ended without a payment is not churn, and a cancellation that takes effect later is not churn yet. The **rate** is the share of the subscriptions paying 30 days ago (started, past any trial, not ended) that have ended since; subscriptions that started within the window are left out of it.
- Each figure is cached for five minutes (`RevenueMetrics::CACHE_SECONDS`), so a busy dashboard runs its queries once.
- A price changed in Stripe without a matching `unit_amount` change shows the old amount here, and a subscription on a price `config/billing.php` does not list is left out of both MRR and the paying count. For accounting, Stripe's own reports remain the reference.

### Support logins

In `/admin`, **Users** lists everyone with an account. **Impersonate** signs you in as that person and takes you to their workspace, under a banner that says who you are signed in as. **Switch back** returns you to the admin panel.

- Only users with `is_admin` can open `/admin` (`User::canAccessPanel()`), and only with [two-factor authentication](#accounts-and-security) set up.
- Only admins can sign in as someone else, and never as another admin (`User::canImpersonate()` and `User::canBeImpersonated()`).
- Every switch is written to the `account_switches` table, and what the admin does while signed in as someone is in the workspace's [audit log](#audit-log) under both names.
- The plugin is set up once for both panels in `app/Support/SupportLogins.php`.

Make your own account an admin from `php artisan tinker`:

```php
App\Models\User::where('email', 'you@example.com')->update(['is_admin' => true]);
```

Read more in the [Filament Account Switcher docs](https://packstub.dev/docs/filament-account-switcher).

### The Projects pattern

Projects are the example to copy for your own models. Seven files make a resource belong to a workspace and count against a plan:

- `database/migrations/workspace/` holds the migrations of the tables a workspace owns. A table there has a ULID key and an indexed `workspace_id` without a database foreign key; the folder's README says why.
- `app/Models/Project.php` has a `workspace()` relationship. Filament uses it to scope every query in the panel and to fill in the workspace on create.
- `app/Enums/ProjectStatus.php` is the `status` column as a backed enum. It carries its own label and colour (`HasLabel`, `HasColor`), so the select in the form and the badge in the table need no extra code.
- `app/Policies/ProjectPolicy.php` decides who may do what from the member's role, and allows creating only while the plan has room. It asks `User::canInWorkspace()`, which looks the role up once per request however many rows the table shows, and again as soon as a membership or a role changes.
- `app/Filament/App/Resources/Projects/Pages/ManageProjects.php` shows "3 of 50 projects on the Pro plan", and swaps **New project** for **Upgrade for more projects** at the limit.
- `app/Actions/CreateProject.php` creates a project, checking the limit once more under a lock per workspace, so two people saving at the same moment cannot pass it. Everything that makes a project goes through it.
- `app/Support/Plans.php` counts the workspace's projects for the `projects` limit.

For a new model:

1. Put its migration in `database/migrations/workspace`, with a ULID key and an indexed `workspace_id`.
2. Add `HasUlids` and the `workspace()` relationship to the model.
3. Delete its rows in `Workspace`'s `deleting` hook, and add them to `DataExport::forWorkspace()`.
4. Write the policy.
5. Create the resource in `app/Filament/App/Resources`.
6. Add a limit if the plan should cap it.

### Tests

Run them with:

```bash
composer test
```

This checks the code style with Pint and runs the Pest suite. In `tests/Feature`:

- `HomePageTest`: the home page links to sign-up and log-in, its pricing follows the billing config and the free trial, the meta tags, canonical and sitemap URLs on `APP_URL` whatever the request's host, the sitemap with and without the legal placeholders, robots.txt in and out of production, the Terms and Privacy placeholders and their switch.
- `SignUpTest`: signing up creates a workspace with the person as owner, the panel opens after e-mail verification, long names, a second workspace, guests and other people's workspaces stay closed.
- `DataExportTest`: what the personal and the workspace exports hold and leave out, the download routes and who may use them.
- `AccountTest`: deleting an account with its password, what goes with it and what stays, the only owner of a shared workspace, support logins.
- `AuthenticationTest`: the password rules, signing in with and without an authenticator app, two-factor authentication required for the admin panel.
- `WorkspaceTest`: reserved slugs, workspace settings by role, deleting a workspace from its settings (owners only, the typed name, Stripe first) and what that removes.
- `OwnershipTest`: transferring ownership from the Members page, owners only, what the new owner may do, the Stripe billing contact following.
- `InvitationTest`: inviting from the Members page, signing up through an invitation, accepting with an existing account, an unverified account that claims an invited address, expired invitations, the seat limit.
- `ProjectLimitTest`: projects stay in their workspace, creating stops at the plan's limit and resumes on a bigger plan, a smaller plan keeps what is there.
- `ProjectAccessTest`: what each role may do with a project, other workspaces' projects stay out of reach by URL and by action, and the list runs the same number of queries however long it is.
- `BillingTest`: subscription webhooks (created, updated, each Stripe status, a trial and its end, deleted, customer deleted) change the plan and the limits; signed and unsigned webhooks; unknown prices.
- `QueuedJobsTest`: queued jobs, stored by a real database queue, never hold a person's password hash, remember token, two-factor secret or e-mail address; a job for a deleted workspace is dropped.
- `SeatBillingTest`: per-seat plans: Checkout with a seat per member, the quantity following members who join, leave or accept an invitation, flat plans left alone, Stripe errors, a quantity put right by the next webhook.
- `BillingPageTest`: the Billing page by role, with and without Stripe keys, monthly and yearly prices, the free trial, Checkout, switching plans and intervals, the customer portal, a past-due subscription, a subscription paused after its trial and resumed, and a payment that needs confirming.
- `AuditLogTest`: what is recorded and by whom (a support login included), plan changes from Stripe and from the admin panel, a deleted workspace and deleted people, pruning, the Activity page by role and by workspace.
- `OnboardingTest`: the Get started steps worked out from real data, the checklist for owners and admins only, going away when done, dismissing it for one workspace and never another, members unable to dismiss it.
- `SocialLoginTest`: buttons only for providers with credentials, signing up and signing in, linking only verified addresses on both sides, two-factor accounts and admins refused, a failed provider, an invitation followed by a social sign-up, connecting and disconnecting on the Profile page (never the last way in), choosing a first password, deleting an account without one, the export.
- `ApiTokenTest`: the API stays inside the token's workspace (lists, by id, creating), validation, the plan's project limit, plans without API access, abilities, missing, wrong, revoked and expired tokens, no browser sessions, the rate limit per token; the API tokens page by role, creating a token shown once, revoking, other workspaces' tokens, the upgrade on the Free plan, deleting a workspace.
- `RevenueMetricsTest`: MRR from monthly, yearly and per-seat prices, what is left out (trials, ended, unknown prices), churn (trials that never paid, cancellations not over yet, the base at the start of the window), plans, sign-ups per day, plan changes, the cache, the dashboard for admins only, no calls to Stripe.
- `SupportLoginTest`: the admin panel is for admins, signing in as a customer and switching back, changing a plan by hand, the admin tables, the Activity of every workspace.
- `SeederTest` and `ScheduleTest`: the demo data seeds once and never in production; the daily housekeeping (invitations, overrides, the audit log) is scheduled.

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
7. **Moving a workspace.** With a shard connection added (`shard_2` here) and the migrations in `database/migrations/workspace` run on it, check with `php artisan tenants:move acme --to=shard_2 --dry-run --exclude=subscriptions,audit_events`, then run it without `--dry-run`. Always pass `--exclude=subscriptions,audit_events`: Cashier's `subscriptions` table and the audit log carry `workspace_id` but stay central, where the webhooks write and the admin panel reads.
8. **Tables that stay central.** Inside a moved workspace the default connection is the workspace's database, so everything central has to name its connection. The kit already does: `config/packstub-teams.php` and `config/packstub-features.php` set `connection` to `DB_CONNECTION`, which is the connection Filament Tenancy treats as central. Filament Account Switcher's tables, notifications and Cashier's subscriptions follow the `User` and `Workspace` models, which are central. Give a central model of your own the `CentralConnection` trait too.
9. **Code outside a workspace's request.** The admin panel, jobs and commands run on the central database. To read what a workspace owns there, run inside it: `$workspace->run(fn () => Project::count())`. The usage counter in `app/Support/Plans.php` and the `deleting` hook on `Workspace` are the two places in the kit that read projects.

Read [Adopting an existing app](https://packstub.dev/docs/filament-tenancy/adopting-an-existing-app) and [Database strategies](https://packstub.dev/docs/filament-tenancy/database-strategies) before you start. The command prints every step it leaves to you.

### Built and launched for you

[XLITE](https://xlite.dev/services/saas-mvp/) builds and launches your SaaS for you, as the SaaS MVP package.

## Security

If you discover a security issue, please e-mail [security@packstub.dev](mailto:security@packstub.dev) instead of using the issue tracker.

## License

The MIT License (MIT). See [LICENSE](LICENSE).
