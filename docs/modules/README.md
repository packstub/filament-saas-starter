# Optional modules

The kit stays small on purpose: what most SaaS products need, and nothing you would have to rip out. Everything else is a **recipe** here: the `composer require` line, the plugin line, the migrations and the few files a module needs, written for this kit's workspaces, plans and support logins. Follow one when your product needs it; nothing is pre-wired, so a module you skip costs nothing.

## How a module plugs in

Four things in the kit keep each recipe short:

- **One list of plugins per panel.** `app/Filament/Plugins.php` has `app()` (the customer panel) and `admin()` (the back office). A recipe adds its plugin there, never in the panel providers. `PluginsTest` checks every plugin listed there is registered.
- **Plans and limits.** A module's limit (workflows, forms) is one more feature in `config/packstub-features.php`, one value per plan, and one `usage()` line in `app/Support/Plans.php`. The **Your plan** page, the overrides in `/admin` and the upgrade button follow on their own.
- **Deleting a workspace or an account.** `Workspace::delete()` dispatches `App\Events\WorkspaceDeleting` and `User::deleteAccount()` dispatches `App\Events\AccountDeleting`, inside the transaction that deletes them. A module removes its own rows in a listener in `app/Listeners`, which Laravel finds by the event its `handle()` method takes. Listen synchronously: a queued listener would run after the rows are gone.
- **Where each table lives.** `tests/Feature/TableRulesTest.php` reads the real schema: every table is either listed as central, with the reason, or a workspace's own table that can move to a database per workspace later (its migration in `database/migrations/workspace`, a ULID key, an indexed `workspace_id`, no foreign key to a central table). A package's tables are central unless its recipe says otherwise, so each recipe adds them to `centralTables()` and says what happens to them when a workspace gets its own database.

Each recipe has the same parts: what you get, what you need, install, the plugin line, plan limits where they fit, where its tables live, what happens when a workspace or an account is deleted, a test to copy, a database per workspace later, and how to remove it.

## Recipes

| Recipe | What your customers get | Package | Status |
| --- | --- | --- | --- |
| [Themes with Nitpick](nitpick.md) | Designed themes and an Appearance page; owners set one for the workspace | `packstub/filament-nitpick` | stable |
| [Session Replay](session-replay.md) | Nothing they see: your team watches their sessions from `/admin`, next to support logins | `packstub/filament-session-replay` | stable |

## Coming

- **Table Views** (`packstub/filament-table-views`): saved views as tabs above the Projects table, per person or shared with the workspace. The recipe comes once it is on Packagist.
- **Holler** (`packstub/filament-holler`): calls between the members of a workspace, from inside the panel. The recipe comes once it is on Packagist.

## For maintainers

Recipes are checked, not just written. Code blocks with a directive after the language (`bash run`, `php file=…`, `php in=… before="…"`, `php use=…`, `php append=…`) are the steps; `scripts/verify-module.sh <name>` copies the kit to a temporary directory, applies them with `scripts/apply-recipe.php`, then runs `composer test` and a fresh `migrate:fresh --seed`. The **Modules** workflow (`.github/workflows/modules.yml`) runs it for every recipe each Monday and on demand; a recipe that waits for a release says so in a `<!-- verify: skip … -->` line and is left out until then. GitHub shows only the language of a code block, so the directives stay out of the reader's way. A project created from the kit gets the recipes but not the scripts or the workflow.
