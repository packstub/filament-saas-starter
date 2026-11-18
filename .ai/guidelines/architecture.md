# Architecture of this SaaS

This app started from the Filament SaaS Starter kit. It is a regular Laravel 13 app on Filament 5; `AGENTS.md` maps every file, these guidelines say how the pieces fit and what to keep true when you change them.

## Two panels, one app

- The customer panel `app` (`app/Providers/Filament/AppPanelProvider.php`) serves `/app/{workspace}`. Filament's single-database tenancy makes `App\Models\Workspace` the tenant: `Filament::getTenant()` is the current workspace, and every resource in `app/Filament/App` is scoped to it through the model's `workspace` relation.
- The back office `admin` (`app/Providers/Filament/AdminPanelProvider.php`) serves `/admin` to users with `is_admin`, and requires two-factor authentication outside the local environment.
- `AppPanelProvider` stays last in `bootstrap/providers.php`. Console commands, queued jobs and webhooks run with the settings of the panel registered last, which must be the customer panel.
- Optional plugins go in `app/Filament/Plugins.php` (`app()`, `admin()`), never straight into a panel provider.

## The pieces and who owns them

- Members, invitations and roles: Filament Teams (`packstub/filament-teams`), roles and their permissions in `config/packstub-teams.php`. Read memberships through the `Teams` facade (`Teams::membersOf()`, `ownersOf()`, `teamsOf()`), and permissions through `User::canInWorkspace($workspace, 'update')`, which caches them for the request.
- Plans and limits: Filament Features (`packstub/filament-features`), features and plans in `config/packstub-features.php`, usage counters in `app/Support/Plans.php`. Check a limit with `Features::canUse('projects', $workspace)`, enforce it with `Features::assertCanUse()` under a lock (see `app/Actions/CreateProject.php`).
- Billing: Laravel Cashier with the workspace as the Stripe customer; the details are in the billing guideline.
- Support logins: Filament Account Switcher, configured once in `app/Support/SupportLogins.php` for both panels.
- The public site: `routes/web.php`, `app/Http/Controllers/HomeController.php`, `resources/views/site`, `config/site.php`.
- The API: `routes/api.php` under `/api/v1`, Sanctum tokens that belong to a workspace (`App\Models\ApiToken`). The authenticated "user" of an API request is the workspace: start every query from `$request->user()`.

## Where new code goes

- A business operation that more than one entry point needs (panel, API, job) is an action in `app/Actions`, a final class with a static method, like `CreateProject::in()`.
- Helpers without state go in `app/Support`, billing code in `app/Billing`; both are final classes (`tests/Unit/ArchTest.php`).
- Enums live in `app/Enums` and implement Filament's `HasLabel` (and `HasColor`/`HasIcon` when shown as badges).
- Settings come from `config/*.php`; application code never calls `env()`.
- Models never import anything from `App\Filament`.
- Event listeners live in `app/Listeners`; Laravel discovers them by the type of their `handle()` argument, so there is no registration list.
