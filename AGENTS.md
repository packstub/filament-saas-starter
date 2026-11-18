# Filament SaaS Starter

A multi-tenant SaaS on Laravel 13 and Filament 5, created from the starter kit `packstub/filament-saas-starter`: sign-up with e-mail verification and two-factor authentication, workspaces on Filament's single-database tenancy, members and invitations (Filament Teams), plans and limits (Filament Features), Stripe billing (Laravel Cashier) and support logins (Filament Account Switcher). The code is meant to be read and changed: clear names, no leftovers, comments only where the reason is not obvious. `README.md` is the tour.

## Commands

```bash
composer setup              # install, .env, key, SQLite, migrate --seed, npm install, npm run build
composer test               # Pint (check) and the Pest suite
composer lint               # Pint
php artisan migrate:fresh --seed
```

## Layout

- `app/Providers/Filament/AppPanelProvider.php`: the customer panel at `/app/{workspace}` (default panel, registered last in `bootstrap/providers.php`), two-factor authentication optional; `AdminPanelProvider.php`: the back office at `/admin`, `is_admin` only, two-factor authentication required outside the local environment.
- `app/Providers/AppServiceProvider.php`: the morph map, `Model::shouldBeStrict()` outside production, `Password::defaults()`.
- `app/Models`: `Workspace` (the tenant, the Cashier billable, `named()`, `reservedSlugs()`, `changePlan()`, `currentSubscription()`, `trialDaysOnOffer()`, `syncPlanFromSubscription()`, the `deleting` hook), `User` (panel access, `canInWorkspace()`, `deleteAccount()` and `workspacesBlockingDeletion()`, impersonation rules, app authentication), `Project` (the example resource, ULID key), `AuditEvent` (the workspaces' audit log: `record()`, `description()`, pruned after `audit.retention_days`); `app/Enums/ProjectStatus.php`, `AuditEventType.php`.
- `app/Support/DataExport.php`: the personal and the workspace data exports (streamed JSON); a new workspace table is added to `forWorkspace()` as well as to the `deleting` hook.
- `app/Support/Plans.php` and `SupportLogins.php`: the Features and Account Switcher plugins, one definition used by both panels; `WorkspacePermissions.php`: a member's permissions remembered for one request (behind `User::canInWorkspace()`), emptied by `app/Listeners/ForgetWorkspacePermissions.php` on every Teams membership event.
- `app/Billing/StripePrices.php` + `config/billing.php`: plan key and interval (`monthly`, `yearly`) to Stripe price and amount, and back; `app/Listeners/SyncWorkspacePlanFromStripe.php`: Cashier's `WebhookHandled` to the workspace's plan, and `customer.deleted` on `WebhookReceived` back to the default plan; `RecordWorkspaceActivity.php`: Teams' membership events into the audit log (plan changes and deletion are recorded by `Workspace`); `RefuseUnsignedStripeWebhooks.php`: no webhooks in production without the signing secret; `UpdateStripeBillingContact.php` (queued): the Stripe customer's e-mail follows a Transfer ownership; `SyncSeatQuantityWithStripe.php` (queued): a per-seat plan's subscription quantity follows the members (`Workspace::syncSeatQuantity()`, `per_seat` in `config/billing.php`, off by default).
- `app/Filament/App`: `Pages/Activity` (the audit log, owners and admins), `Pages/Auth/EditProfile` (Filament's profile page plus Download your data and Delete account), `Pages/Billing`, `Pages/Tenancy/*` (`EditWorkspace` also holds Export workspace data and Delete workspace, owners only), `Resources/Projects`, `Widgets/WorkspaceOverview`; `app/Filament/Admin`: Users (with Impersonate), Workspaces (with Change plan and a link to its Activity) and AuditEvents (Activity: every workspace's audit log, read-only).
- `config/packstub-features.php`: features and plans; `config/packstub-teams.php`: roles, the `workspace_user` pivot.
- `database/seeders/DatabaseSeeder.php`: the demo accounts, idempotent, skipped in production; its e-mail constants are the developer logins.
- `tests/Feature`: sign-up, authentication, workspaces, invitations, the project limit and access by role, billing webhooks, the Billing page and per-seat billing, support logins and the admin tables, the seeder, the schedule; `tests/Unit/ArchTest.php`: architecture tests. Helpers in `tests/Pest.php`: `workspace()`, `memberOf()`, `admin()`, `inWorkspace()`, `inAdminPanel()`, `stripeSubscriptionEvent()`, `stripeSubscription()`, `fakeStripe()` (calls in `->requests`, their parameters in `->params`), `countQueries()`.

## Conventions

- PHP 8.3+, Pint, Pest. Every change comes with a test; `composer test` passes before finishing.
- Tests: datasets instead of loops, `expect(fn () => …)->toThrow()` or an assertion on the outcome instead of `try`/`catch`, factory states (`admin()`, `withAppAuthentication()`, `unverified()`, `paused()`, `onPlan()`) instead of mocked models, `fakeStripe()` for the calls to Stripe. A test user signed in to `/admin` needs `admin()`: the panel requires two-factor authentication.
- Models are strict outside production: eager-load what a table or a policy reads (`getEloquentQuery()`), and give `UserFactory` a value for every new `users` column (`actingAs()` users are never re-read from the database).
- Both panels use the same `Plans::plugin()`, and `AppPanelProvider` stays last in `bootstrap/providers.php`: console commands, queued jobs and webhooks run with the settings of the panel registered last.
- The migrations in `database/migrations` are the published ones of each package, renamed only in their date prefix; Cashier's point at `workspaces` and `workspace_id`.
- What a workspace owns has its migration in `database/migrations/workspace`: a ULID key, an indexed `workspace_id`, no database foreign keys to `workspaces` or `users`, rows removed in `Workspace`'s `deleting` hook (which also removes its Cashier subscriptions and feature overrides). `Workspace::delete()` wraps the hook and the row in one transaction, so delete a workspace through the model (`$workspace->delete()`), never with a query (`Workspace::where(…)->delete()` skips both). A subscription Stripe no longer has (`resource_missing`) does not stop the delete; any other Stripe error does. That keeps a later move to a database per workspace (Filament Tenancy) mechanical.
- `config/packstub-teams.php` and `config/packstub-features.php` name their `connection` (`DB_CONNECTION`) instead of leaving it null, so their tables stay on the app's own database if workspaces ever get databases of their own.
- App code reads memberships through the `Teams` facade (`Teams::ownersOf()`, `membersOf()`, `teamsOf()`), not through the traits' relations, and never queries a workspace's own tables from the admin panel. The one exception is the counts of the admin tables, which need a relation name: `withCount('members')` and `withCount('joinedTeams')`.
- The audit log (`audit_events`) is central on purpose, like billing and `account_switches`: a ULID key, `workspace_id` and names kept as they were, no foreign keys. Record new kinds of events through `AuditEvent::record()`; never edit an entry.
