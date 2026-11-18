# Workspace data

The tables are split in two on purpose, so that a workspace can later get a database of its own (Filament Tenancy) without rewriting the app.

## Central tables

Accounts, memberships, invitations, billing, feature overrides, support logins, the audit log, API tokens and the cache, jobs and sessions tables. Their migrations are in `database/migrations`. `tests/Feature/TableRulesTest.php` lists every one in `centralTables()`, each with the reason it is central.

## A workspace's own tables

What a workspace owns (projects, and anything like them) follows four rules, enforced by `TableRulesTest`:

1. The migration lives in `database/migrations/workspace`.
2. The primary key is a ULID: `$table->ulid('id')->primary()` in the migration, `HasUlids` on the model.
3. `workspace_id` is indexed and has no database foreign key: `$table->foreignId('workspace_id')->index()`, never `->constrained()`. The same goes for any column pointing at `users`.
4. Its rows go when the workspace goes: in the `deleting` hook of `App\Models\Workspace`, or, for a module, in a synchronous listener of `App\Events\WorkspaceDeleting`.

Also add the table to `App\Support\DataExport::forWorkspace()`, so the owner's export holds it.

## Deleting

- Delete a workspace with `$workspace->delete()`. It runs the hook and the row in one transaction and cancels the Stripe subscription first. `Workspace::query()->...->delete()` skips all of that.
- Delete an account with `$user->deleteAccount()`, which refuses while the person is the only owner of a workspace with other members, deletes the workspaces only they used, and dispatches `App\Events\AccountDeleting`.
- Listeners of `WorkspaceDeleting` and `AccountDeleting` must not implement `ShouldQueue`: they run inside the transaction, and a queued one would find the rows gone (`tests/Feature/DeletionEventsTest.php` fails on one).
- `WorkspaceDeleting` fires first in the `deleting` hook, before the Stripe cancellation and the kit's own rows. In its listeners (and `AccountDeleting`'s), do in the transaction only what it can undo, database rows; delete files and call other services in `DB::afterCommit()`, like `app/Listeners/DeleteWorkspaceFiles.php`.
- Files belong to their owner too: what a workspace or a person uploaded is removed in those same listeners, after the commit.
- `rowsLeftOf($workspace)` in `tests/Pest.php` lists the rows any table still holds for a workspace; assert it is empty after deleting one in a new feature's test.

## Reading workspace data

- Inside the customer panel, scope through the tenant (`Filament::getTenant()`, `$workspace->projects()`), never with a bare `Model::query()`.
- The admin panel never queries a workspace's own tables; it works with central tables only (counts through `withCount('members')` and `withCount('joinedTeams')` are the exception).
- Models are strict outside production (`Model::shouldBeStrict()`): eager-load what a table or a policy reads.

## The audit log

`audit_events` is central: a ULID key, `workspace_id` and names kept as text, no foreign keys. Write entries only with `AuditEvent::record($workspace, AuditEventType::Something, [...])`, add a case to `App\Enums\AuditEventType` for a new kind, and never update an entry.
