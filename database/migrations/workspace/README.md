# Workspace migrations

The tables a workspace owns (its data, as opposed to accounts, memberships and billing) have their migrations here. `php artisan migrate` runs them with the others; `AppServiceProvider` registers the folder.

Keeping them apart costs nothing today and makes a later move to a database per workspace a matter of pointing the migrator at this folder. Three rules keep a table movable:

- a ULID primary key (`$table->ulid('id')->primary()` and `HasUlids` on the model);
- an indexed `workspace_id` with no database foreign key, and none to `users` either;
- rows removed when the workspace is deleted: in `Workspace`'s `deleting` hook, or in a listener of `App\Events\WorkspaceDeleting`, which the hook dispatches inside the same transaction (a module does it that way, see `docs/modules`). Listen synchronously, never on the queue.

Add the table to `App\Support\DataExport::forWorkspace()` too, so an owner's export holds everything the workspace owns.
