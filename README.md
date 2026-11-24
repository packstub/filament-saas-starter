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
php artisan storage:link
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
- **Workspace settings** is `EditWorkspace.php` next to it, open to owners and admins through `app/Policies/WorkspacePolicy.php`. It holds the workspace's name and its **Logo**, shown in the workspace menu and at the top of the workspace's e-mails. See [Uploaded files](#uploaded-files).
- **Delete workspace**, at the bottom of Workspace settings, is for owners only (`WorkspacePolicy::delete()`), never during a support login, and asks for the workspace's name. It runs `$workspace->delete()`: the Stripe subscription is cancelled first, then the projects, members, invitations, subscription rows and overrides go in one transaction. If Stripe refuses, nothing is deleted and the owner is told. Afterwards the panel opens another of their workspaces, or the **New workspace** page.
- **Get started**, on the dashboard of a new workspace (`app/Filament/App/Widgets/GettingStarted.php`), shows owners and admins four steps: invite a teammate, create a first project, choose a plan, turn on two-factor authentication. Each one is worked out from what is really there (members and invitations, projects, a subscription or a paid plan, the person's authenticator app), so it ticks itself off. The checklist goes once every step is done, or when someone clicks **Dismiss**, which hides it for the whole workspace (`workspaces.onboarding_dismissed_at`). Change the steps in `GettingStarted::steps()`.
- `User::getDefaultTenant()` picks the workspace a person lands in after signing in.

### Accounts and security

- **Passwords** follow `Password::defaults()` in `app/Providers/AppServiceProvider.php`, on the sign-up, password reset and profile pages: at least 12 characters and not found in known data leaks in production, at least 8 elsewhere so the demo accounts can keep `password`.
- **Two-factor authentication** is Filament's own, with an authenticator app and recovery codes. Customers switch it on from their **Profile** page. The admin panel requires it: an admin without it is sent to set it up before any page opens. In the local environment `/admin` skips that step, so the demo admin gets in with one click.
- The secret and the recovery codes are stored encrypted in two columns of the `users` table.
- **Download your data**, on the **Profile** page, gives a person one JSON file with everything the app holds about them: their account, the workspaces they are in and their role, their notifications, and every support login into their account. **Export workspace data**, in Workspace settings, does the same for a workspace, for owners only (`WorkspacePolicy::export()`): members, invitations, subscriptions, overrides and projects. Both come from `app/Support/DataExport.php` and are plain GET downloads (`/exports/me`, `/exports/workspaces/{slug}`, in `app/Http/Controllers`) streamed to the browser as they are read, so a workspace with many projects never sits in memory whole. The buttons link there rather than returning the file from a Filament action, which Livewire would buffer whole. A table you add for a workspace belongs in `DataExport::forWorkspace()` too.
- **Delete account** is at the bottom of the **Profile** page (`app/Filament/App/Pages/Auth/EditProfile.php`), behind the person's password. `User::deleteAccount()` deletes every workspace where they are the only member (cancelling its subscription first) and takes them out of the others. Someone who is the only owner of a workspace others still use is asked to hand it over under **Members** (**Transfer ownership**) or delete it first, so no workspace is left without an owner. The button is hidden during a support login. Filament Account Switcher's own log loses the support logins into a deleted account (its `account_switches` rows go with the user); the workspaces' audit logs keep what support did, under "Deleted user". A module that keeps something about a person removes it in a listener of `App\Events\AccountDeleting`, dispatched inside the same transaction.
- **Google and GitHub sign-in** ([Laravel Socialite](https://laravel.com/docs/socialite)) turn on once their keys are in `.env`; until then no button shows. See [Signing in with Google and GitHub](#signing-in-with-google-and-github).
- **Profile picture**: on the **Profile** page, shown in the user menu instead of the initials. See [Uploaded files](#uploaded-files).
- **Strict models**: outside production, `Model::shouldBeStrict()` turns a lazy-loaded relation (an N+1 query), a missing attribute and a field left out of `Fillable` into an error, so you find them while developing.

### Uploaded files

Workspace logos and profile pictures are Filament file uploads, set up in `config/uploads.php` and `app/Support/Uploads.php`:

- **Where**: the `public` disk by default (`storage/app/public`, linked into `public/` by `php artisan storage:link`, which `composer setup` runs). Set `UPLOADS_DISK=s3` and the `AWS_*` variables for an S3 or S3-compatible bucket, after `composer require league/flysystem-aws-s3-v3 "^3.0"`. Files are stored with `public` visibility (`UPLOADS_VISIBILITY`); a bucket without per-file ACLs needs a public-read policy. On Laravel Cloud, attach a public bucket named `public` ([the guide](docs/deploy/laravel-cloud.md)). With several servers or replicas the default disk must be a shared bucket too, for Livewire's temporary uploads.
- **What**: PNG, JPEG or WebP, up to `UPLOADS_MAX_SIZE` kilobytes (1024 by default). No SVG: a picture people upload is served from your domain, and an SVG can carry scripts.
- **Who owns it**: every file sits in its owner's folder, `workspaces/{id}/…` or `users/{id}/…`. The models refuse a path outside it (`workspaces.logo_path`, `users.avatar_path`), so nobody can point their workspace at another one's file, and a replaced logo or picture is deleted from the disk.
- **Deleting**: `app/Listeners/DeleteWorkspaceFiles.php` and `DeleteAccountFiles.php` remove the owner's whole folder when the workspace or the account is deleted, once the deletion is committed; a deletion that fails keeps the files. A file your own code stores for a workspace goes under `Uploads::workspaceDirectory($workspace)` and is removed with it for free.
- **Public by link**: anyone with a file's URL can open it. Keep files that must stay private on a private disk and hand them out with `Storage::temporaryUrl()`.

### E-mails

Every e-mail the app sends, Filament's verification and password reset included, uses one layout: `resources/views/vendor/mail/html/message.blade.php` with the `saas` theme (`html/themes/saas.css`, Laravel's default in the panel's Indigo; `markdown` in `config/mail.php`). The top shows `APP_NAME`, or the workspace's name and **Logo** when the e-mail is about a workspace; the bottom shows `SITE_COMPANY` and `SITE_CONTACT_EMAIL`. Change the colours in the theme, the wording in the views. Names people choose (a workspace's, a person's) appear in these e-mails, so `AppServiceProvider` turns on `Markdown::withSecuredEncoding()`: Markdown inside a `{{ }}` is printed as text, and a workspace called `[Verify](https://…)` never becomes a link. Keep links and other Markdown in the view, outside `{{ }}`.

| E-mail | Sent to | From |
| --- | --- | --- |
| Invitation | The invited address | Filament Teams, with the workspace's logo (`resources/views/vendor/packstub-teams/mail/invitation.blade.php`) |
| Payment failed | The workspace's owners, also in the bell menu | `app/Notifications/PaymentFailed.php`, when a Stripe webhook moves the subscription to past due (`app/Listeners/NotifyOwnersOfFailedPayment.php`), once per failure |
| Workspace deleted | Everyone who was in it, and who deleted it | `app/Notifications/WorkspaceDeleted.php`, after the deletion is committed (`app/Listeners/SendWorkspaceDeletedNotice.php`) |
| Account deleted | The deleted account's address | `app/Notifications/AccountDeleted.php`, after the deletion is committed (`app/Listeners/SendAccountDeletedNotice.php`) |

Receipts and invoices come from Stripe: turn them on in Stripe's customer e-mail settings, where you can also brand them. All of these are queued, so a worker must run in production. Preview one with `php artisan tinker` and `(new App\Notifications\AccountDeleted('Ada'))->toMail(new Illuminate\Notifications\AnonymousNotifiable)->render()`, or send them to [Mailpit](https://mailpit.axllent.org) locally with `MAIL_MAILER=smtp` and `MAIL_PORT=1025`.

### Languages

The kit's own text lives in `lang/en` and `lang/es` (Spanish, complete, as the example), behind `__()`. Filament is translated too; the pages of the Packstub plugins (Members, Invitations, Your plan) are English-only for now, and can be translated in `lang/vendor` (see the guide). A signed-in person picks a **Language** on the **Profile** page, which their e-mails follow too; a visitor picks one in the home page's footer. The languages on offer are `locales` in `config/app.php`. [docs/translations.md](docs/translations.md) shows where each text lives and how to add a language; `TranslationsTest` fails when a key is missing in one of them.

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
- **A copy made before 1.0.0** (from the template between 2026-11-17 and the release) has `users.password` as `NOT NULL`. To add Google and GitHub sign-up to it, add a migration with `$table->string('password')->nullable()->change();` on `users`. A new project needs nothing: the column is nullable in `create_users_table`.

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

- Tokens are [Laravel Sanctum](https://laravel.com/docs/sanctum) personal access tokens whose owner is the **workspace**, not a person (`HasApiTokens` on `Workspace`, `App\Models\ApiToken`). The table shows who created each token. That person saw it in full, so their tokens are revoked as soon as they are no longer an owner or admin: removed from the workspace, made a member, or gone with their account (`app/Listeners/RevokeApiTokensOfFormerManagers.php`, with an entry in the audit log). Tokens made by the people who still manage the workspace keep working. Deleting a workspace deletes its tokens.
- The API lives in `routes/api.php` under `/api/v1`, with `app/Http/Controllers/Api/V1/ProjectController.php` as the example: list (`GET /api/v1/projects`, paginated), show (`GET /api/v1/projects/{id}`) and create (`POST /api/v1/projects`). A request authenticates as the workspace (`$request->user()` is the `Workspace`), so every query starts from it and another workspace's records are out of reach.
- Each route names the ability it needs (`abilities:projects:read`); add yours to `ApiToken::ABILITIES` and they appear on the page.
- The API is a plan feature, `api_access` in `config/packstub-features.php` (Pro and Business). On a plan without it the page offers an upgrade, and the API answers 403, also for tokens created before a downgrade (`app/Http/Middleware/EnsureWorkspaceHasApiAccess.php`).
- Creating a project goes through `App\Actions\CreateProject`, so the plan's `projects` limit holds: the API answers 403 with the limit and an `upgrade_url`.
- Each token may make 60 requests a minute (the `api` rate limiter in `AppServiceProvider`). Errors are JSON, whatever the client asks for.
- The API answers to tokens only, never to a signed-in browser session (`guard` is empty in `config/sanctum.php`).
- Expired tokens are deleted a day after they expire (`sanctum:prune-expired`, scheduled daily).

```bash
curl https://my-app.test/api/v1/projects \
  -H "Authorization: Bearer 1|..." -H "Accept: application/json"

curl https://my-app.test/api/v1/projects \
  -H "Authorization: Bearer 1|..." -H "Accept: application/json" \
  -H "Content-Type: application/json" -d '{"name": "Website relaunch"}'
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
3. Delete its rows in `Workspace`'s `deleting` hook, and add them to `DataExport::forWorkspace()`. Code you would rather keep out of the model (a module's tables, say) listens to `App\Events\WorkspaceDeleting` instead: the hook dispatches it inside the same transaction, while the workspace's projects are still there.
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
- `MailTest`: the branded layout and theme on every e-mail, the workspace's name and logo on its invitation, the failed payment notice to owners only and once per failure, the deletion notices to members and to a deleted account, none after a rollback.
- `TranslationsTest`: every key and placeholder in every language, a person's language in the panel and their e-mails, the home page's language switch.
- `UploadsTest`: logos and profile pictures stored in their owner's folder and shown in the menus, size and type limits, a replaced file removed, never another workspace's or person's file, the folders removed when a workspace or an account is deleted (and kept when the deletion fails), the exports.
- `DataExportTest`: what the personal and the workspace exports hold and leave out, the download routes and who may use them.
- `AccountTest`: deleting an account with its password, what goes with it and what stays, the only owner of a shared workspace, support logins.
- `AuthenticationTest`: the password rules, signing in with and without an authenticator app, two-factor authentication required for the admin panel.
- `WorkspaceTest`: reserved slugs, workspace settings by role, deleting a workspace from its settings (owners only, the typed name, Stripe first) and what that removes.
- `TableRulesTest`: every table is either central (listed, with the reason) or a workspace's own one with its migration in `database/migrations/workspace`, a ULID key, an indexed `workspace_id` and no foreign key to a central table; and a deleted workspace leaves no row behind in any table (`rowsLeftOf()` in `tests/Pest.php`).
- `PluginsTest`: the optional plugins listed in `app/Filament/Plugins.php` are registered on their panel.
- `DeletionEventsTest`: the events a module listens to when a workspace or an account is deleted, inside the transaction, and never on the queue.
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
- `AgentGuidelinesTest`: every file, class and method that `AGENTS.md` and the guidelines in `.ai` name exists.
- `SeederTest` and `ScheduleTest`: the demo data seeds once and never in production; the daily housekeeping (invitations, overrides, the audit log, expired API tokens) is scheduled.

`tests/Unit/ArchTest.php` holds the architecture tests: Pest's `php`, `security` and `laravel` presets and a few rules of the kit's own.

The webhook tests post Stripe's payloads to the app, and `fakeStripe()` in `tests/Pest.php` answers the calls the app makes to Stripe, so the suite needs no Stripe account. `.github/workflows/tests.yml` runs it on PHP 8.3, 8.4 and 8.5.

### Coding agents

The kit is ready for Claude Code, Cursor, Codex and the other coding agents:

- `AGENTS.md` (and `CLAUDE.md`, a link to it) maps the code and lists the conventions.
- `.ai/guidelines` explains the parts an agent is most likely to get wrong: the architecture, the rules for a workspace's tables, billing, testing, how to add a resource, a plan limit or a module, and a list of what never to do.
- `.ai/skills/adding-a-workspace-resource` walks an agent through a new kind of record from the migration to the tests.

[Laravel Boost](https://laravel.com/docs/boost) is installed as a dev dependency. Run it once and pick your agents:

```bash
php artisan boost:install
```

It adds Boost's MCP server (the database schema, read-only queries, the logs, a search of the Laravel docs for the versions you have installed), Laravel's own guidelines and skills, and copies the guidelines and skills from `.ai` into each agent's files. Your text in `AGENTS.md` stays; Boost writes inside its own `<laravel-boost-guidelines>` block. The files it generates for each agent (`.mcp.json`, `.claude/skills`, `.agents/skills`, `opencode.json`) are in `.gitignore`; commit `boost.json` if your team should share the same setup. After `composer update`, run `php artisan boost:update`. Edit the sources in `.ai`, never the copies.

## Optional modules

The kit leaves out what only some products need. Each of these is a short recipe in [`docs/modules`](docs/modules/README.md): the `composer require` line, one line in `app/Filament/Plugins.php`, the migrations, a plan limit where one fits, what happens when a workspace or an account is deleted, a test to copy, and how to take it out again.

| Recipe | What it adds |
| --- | --- |
| [Themes with Nitpick](docs/modules/nitpick.md) | Designed themes and an Appearance page in the app panel; owners set one for their workspace |
| [Session Replay](docs/modules/session-replay.md) | Recordings of customer sessions, watched from `/admin` next to support logins |
| [Workflows with Flow](docs/modules/flow.md) | Automations per workspace, with the number of workflows as a plan limit |
| [Forms with Form Builder](docs/modules/form-builder.md) | Forms per workspace, hosted or embedded, with the number of forms as a plan limit |
| [A Projects board with Kanban](docs/modules/kanban.md) | The Projects resource as a board by status (a beta package) |
| [An AI assistant with credits](docs/modules/ai.md) | A chat assistant per workspace, paid for by monthly AI credits from the plan |

Four things in the kit keep the recipes short: the plugin lists in `app/Filament/Plugins.php`, the `App\Events\WorkspaceDeleting` and `App\Events\AccountDeleting` events a module cleans up in, the one-value-per-line plans in `config/packstub-features.php`, and `TableRulesTest`, which makes each module say where its tables live.

## Deploying

The app needs a MySQL or PostgreSQL database, a queue worker, the scheduler and a mail provider in production, and a Stripe webhook once you sell plans. The guides in [`docs/deploy`](docs/deploy) walk through each host:

- [Laravel Cloud](docs/deploy/laravel-cloud.md): build and deploy commands, a managed queue, the scheduler toggle, buckets for uploads, secrets, your domain and the live Stripe webhook.
- [Your own server](docs/deploy/server.md), with Forge, Ploi or by hand: the deploy script, a Supervisor worker (and when Horizon is worth it), the cron entry, Redis, files and backups.
- [Environment variables](docs/deploy/environment.md): every value the app reads, and what it should be in production.
- [Production checklist](docs/deploy/production-checklist.md): what to check before the first customer, from `APP_DEBUG` to two-factor authentication for every admin.

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
