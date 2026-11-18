# Never do this here

Each of these looks harmless and breaks something the tests or the customers rely on.

- Never delete a workspace with a query (`Workspace::where(…)->delete()`): it skips the Stripe cancellation, the `deleting` hook and the transaction. Use `$workspace->delete()`.
- Never add a database foreign key from a workspace's table to `workspaces` or `users`, a non-ULID key to one, or put its migration outside `database/migrations/workspace`.
- Never queue a listener of `WorkspaceDeleting`, `AccountDeleting` or Filament Teams' events, and never put a `User` model in a queued job; pass ids.
- Never set a workspace's plan from anything the browser sends (the Checkout success URL, a form field in the customer panel).
- Never call the Stripe API from a test, from `RevenueMetrics`, or for every page view.
- Never query a workspace's own tables from the admin panel, or read another workspace's rows in the customer panel (scope through the tenant).
- Never call `env()` outside `config/`, and never commit `.env`, API keys or Stripe secrets.
- Never edit or delete an `audit_events` row by hand; it is a log.
- Never register a plugin straight in a panel provider (use `app/Filament/Plugins.php`), and never move `AppPanelProvider` from the last place in `bootstrap/providers.php`.
- Never return a file download from a Filament action: Livewire buffers it whole. Link to a route that streams it, like the data exports.
- Never sign in an admin through Google or GitHub, or skip two-factor authentication on a social sign-in.
- Never store an uploaded file outside its owner's folder (`App\Support\Uploads::workspaceDirectory()` or `userDirectory()`): deletion removes files by that folder, and the models refuse a path outside it.
- Never accept SVG or HTML uploads on a public disk: they run scripts on your domain.
- Never write text a person reads straight into a page, a notification or a view: add a key to every `lang/<locale>` file and call `__()` (`docs/translations.md`).
- Never leave a test red or Pint unhappy: `composer test` passes before a change is done.
