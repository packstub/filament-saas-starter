# Extending the app

## A new resource that a workspace owns

Copy the shape of Projects; the `adding-a-workspace-resource` skill has the full checklist. In short: a migration in `database/migrations/workspace` (ULID key, indexed `workspace_id`, no foreign keys), a model with `HasUlids` and a `workspace()` relation, a policy that asks `User::canInWorkspace()`, a resource in `app/Filament/App/Resources`, its rows removed in `Workspace`'s `deleting` hook, its rows in `DataExport::forWorkspace()`, and tests for access by role and for deletion.

## A new plan limit

1. Add the feature, with a value for every plan, in `config/packstub-features.php` (one value per line).
2. Count its usage with a `->usage('name', fn (?Workspace $workspace): int => …)` line in `app/Support/Plans.php`.
3. Check it in the policy (`Features::canUse()`) for the UI, and enforce it where the record is created with `Features::assertCanUse()` under a lock per workspace, like `app/Actions/CreateProject.php`.

The **Your plan** page, the overrides in `/admin` and the upgrade button follow on their own.

## A new optional module

`docs/modules` holds a recipe per package (Nitpick, Session Replay, Flow, Form Builder, Kanban, AI with credits). Follow the recipe's steps in order: the plugin goes in `app/Filament/Plugins.php`, its tables in `centralTables()` of `tests/Feature/TableRulesTest.php` with the reason, its cleanup in listeners of `App\Events\WorkspaceDeleting` and `App\Events\AccountDeleting`, and the test file the recipe gives into `tests/Feature`. For a package without a recipe, write the same parts.

## A new kind of audit event

Add a case to `App\Enums\AuditEventType` with its label, colour and icon, and write it with `AuditEvent::record()` where it happens.

## A new API endpoint

Add the route in `routes/api.php` inside the `v1` group with its `abilities:…` middleware, add the ability to `ApiToken::ABILITIES`, start every query from `$request->user()` (the workspace), and go through the same action as the panel so plan limits hold (`tests/Feature/ApiTokenTest.php` shows the tests).

## A new public page

A route in `routes/web.php`, a view in `resources/views/site` on the `x-site.layout` component, and its route name in the sitemap route.
