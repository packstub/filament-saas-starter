---
name: adding-a-workspace-resource
description: Add a new kind of record that belongs to a workspace (like Projects), with its migration, model, policy, Filament resource, plan limit, deletion, export and tests. Use when asked to add a model, table, resource or CRUD page to the customer panel.
---

# Adding a workspace resource

Projects is the worked example: read `app/Models/Project.php`, `app/Policies/ProjectPolicy.php`, `app/Filament/App/Resources/Projects`, `app/Actions/CreateProject.php` and `tests/Feature/ProjectAccessTest.php` first, and copy their shape. Below, `Invoice` stands for the new record.

## 1. Migration

`php artisan make:migration create_invoices_table --path=database/migrations/workspace`

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->ulid('id')->primary();
    // Indexed, no foreign key: Workspace's deleting hook removes a workspace's invoices.
    $table->foreignId('workspace_id')->index();
    // A person is referenced the same way, never with ->constrained().
    $table->foreignId('created_by')->nullable()->index();
    $table->string('number');
    $table->timestamps();
});
```

## 2. Model and factory

`HasUlids`, `#[Fillable([...])]`, casts in `casts()`, a `workspace(): BelongsTo` relation (Filament scopes the panel by it), a `invoices(): HasMany` relation on `Workspace`, and a factory. Enums for a status go in `app/Enums` and implement `HasLabel`.

## 3. Policy

`app/Policies/InvoicePolicy.php`: every method asks `$user->canInWorkspace($invoice->workspace, 'read' | 'create' | 'update' | 'delete')`; `create()` reads the workspace from `Features::currentScope()`. The permissions of each role are in `config/packstub-teams.php`.

## 4. Filament resource

`php artisan make:filament-resource Invoice --panel=app` (add `--simple` for a modal-only resource, like Projects). It lives in `app/Filament/App/Resources/Invoices`. Eager-load in `getEloquentQuery()` whatever the table or the policy reads.

## 5. Plan limit (when it is one)

A feature with a value per plan in `config/packstub-features.php`, a `->usage('invoices', …)` line in `app/Support/Plans.php`, `Features::canUse('invoices', $workspace)` in the policy's `create()`, and an action in `app/Actions` that calls `Features::assertCanUse()` under `Cache::lock('invoices:'.$workspace->getKey())`, used by the panel and the API alike.

## 6. Deletion and export

- In `App\Models\Workspace`'s `deleting` hook: `$workspace->invoices()->delete();` next to the projects line.
- In `App\Support\DataExport::forWorkspace()`: the invoices, so the owner's export holds them.

## 7. Tests

In `tests/Feature/InvoiceAccessTest.php`, with `workspace()`, `memberOf()` and `inWorkspace()`:

- each role sees, creates, edits and deletes what `config/packstub-teams.php` allows (a dataset of roles);
- a member of another workspace sees none of it, and gets a 404 on its URL;
- the plan limit stops the next one, in the panel and through the action;
- deleting the workspace removes its invoices; the export contains them.

`TableRulesTest` checks the table on its own. Run `composer test`.
