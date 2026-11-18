# Workflows with Flow

[Flow](https://github.com/packstub/filament-flow) gives each workspace a **Workflows** page: a visual editor where your customers wire triggers (a project created or updated, a schedule, a webhook, a button) to actions (send an e-mail or a notification, call a URL, wait, ask for an approval), with runs, approvals and secrets of their own. The number of workflows is a plan limit, like projects and seats.

## What you need

- `packstub/filament-flow` 1.6 or later, stable on Packagist, PHP 8.3+.
- A queue worker in production for **Wait** steps and queued runs (`QUEUE_CONNECTION=database` or Redis, `php artisan queue:work`); the kit's `sync` default is fine while you try it.
- The scheduler running for **Schedule** triggers: Flow adds `packstub-flow:cron` to it by itself.
- The **Ask AI** action needs `packstub/agents`; see the [AI recipe](ai.md).

## 1. Install

```bash run
composer require packstub/filament-flow:^1.6
php artisan vendor:publish --tag=packstub-flow-migrations
php artisan migrate
```

The migration adds seven tables: `flow_workflows`, `flow_workflow_triggers`, `flow_workflow_versions`, `flow_workflow_runs`, `flow_workflow_steps`, `flow_workflow_waits` and `flow_secrets`.

Tell Flow how to find the workspace of a record, so a project created from the API (outside the panel) runs that workspace's workflows. In `.env`, and in `.env.example` for the next person:

```dotenv append=.env
# Flow: a record's workspace is its `workspace` relationship.
PACKSTUB_FLOW_TENANT_RELATIONSHIP=workspace
```

## 2. Let projects start workflows

The **Record created / updated / deleted** triggers list the models that use Flow's `HasWorkflows` trait. In `app/Models/Project.php`:

```php use=app/Models/Project.php
use Packstub\Flow\Concerns\HasWorkflows;
```

```php in=app/Models/Project.php after="class Project extends Model|use HasUlids;"

// Created, updated and deleted projects can start a workspace's workflows (Flow).
use HasWorkflows;
```

Add it to any model of yours that should start workflows.

## 3. A plan limit on workflows

In `config/packstub-features.php`, a feature:

```php in=config/packstub-features.php after="'features' => [|'seats' =>"
'workflows' => ['label' => 'Workflows', 'type' => 'limit', 'default' => 1, 'unit' => 'workflows', 'group' => 'Workspace', 'description' => 'Automations in this workspace.'],
```

and a value per plan (`null` is unlimited):

```php in=config/packstub-features.php after="'plans' => [|'free' =>|'seats' =>"
'workflows' => 1,
```

```php in=config/packstub-features.php after="'plans' => [|'pro' =>|'seats' =>"
'workflows' => 25,
```

```php in=config/packstub-features.php after="'plans' => [|'business' =>|'seats' =>"
'workflows' => null,
```

Count them in `app/Support/Plans.php`, so **Your plan** shows "3 of 25 workflows":

```php use=app/Support/Plans.php
use Packstub\Flow\Models\Workflow;
```

```php in=app/Support/Plans.php after="->usage('seats',"
->usage('workflows', fn (?Workspace $workspace): int => $workspace ? Workflow::query()->ofTenant($workspace)->count() : 0)
```

## 4. Register the plugin on the app panel

In `app/Filament/Plugins.php`:

```php use=app/Filament/Plugins.php
use App\Models\Workspace;
use Packstub\Features\Facades\Features;
use Packstub\Flow\FlowPlugin;
```

```php in=app/Filament/Plugins.php before="function app()|];"
// Workflows, runs, approvals and secrets per workspace; how many workflows comes from the plan.
FlowPlugin::make()
    ->navigationGroup('Automation')
    ->maxWorkflows(fn (?Workspace $workspace): ?int => $workspace ? Features::limit('workflows', $workspace) : null),
```

The panel's tenancy scopes everything to the current workspace: workflows, secrets, runs and approvals. At the limit, **New workflow** is disabled with a tooltip. Global workflows (no workspace) can only be made in code or from a panel without tenancy; the kit's admin panel does not list them.

Every member of a workspace may build and run its workflows. To keep that to owners and admins, add `->authorize(fn (): bool => (bool) auth()->user()?->can('update', Filament::getTenant()))` to the plugin, or write a `WorkflowPolicy`; see [Authorization](https://github.com/packstub/filament-flow/blob/main/docs/configuration.md#authorization).

## 5. Say where its tables live

Flow's tables carry their workspace in `tenant_type` and `tenant_id` and keep UUID keys; they stay central (see below). In `centralTables()` in `tests/Feature/TableRulesTest.php`:

```php in=tests/Feature/TableRulesTest.php before="function centralTables()|];"
// Flow: workflows and runs carry their workspace (tenant_type, tenant_id).
'flow_workflows' => 'per workspace by tenant_type and tenant_id, global ones too',
'flow_workflow_triggers' => 'workflows',
'flow_workflow_versions' => 'workflows',
'flow_workflow_runs' => 'workflows',
'flow_workflow_steps' => 'workflows',
'flow_workflow_waits' => 'workflows',
'flow_secrets' => 'per workspace by tenant_type and tenant_id',
```

## 6. Delete a workspace's workflows with it

Create `app/Listeners/DeleteWorkspaceWorkflows.php`:

```php file=app/Listeners/DeleteWorkspaceWorkflows.php
<?php

namespace App\Listeners;

use App\Events\WorkspaceDeleting;
use Packstub\Flow\Models\Secret;
use Packstub\Flow\Models\Workflow;
use Packstub\Flow\Models\WorkflowRun;
use Packstub\Flow\Models\WorkflowWait;

/**
 * A deleted workspace's workflows (with their versions, triggers, runs
 * and steps, by foreign key), its secrets, and the runs and waits global
 * workflows had for it.
 */
class DeleteWorkspaceWorkflows
{
    public function handle(WorkspaceDeleting $event): void
    {
        $workspace = $event->workspace;

        WorkflowWait::query()->withoutGlobalScopes()->ofTenant($workspace)->delete();
        WorkflowRun::query()->withoutGlobalScopes()->ofTenant($workspace)->delete();
        Workflow::query()->withoutGlobalScopes()->ofTenant($workspace)->delete();
        Secret::query()->withoutGlobalScopes()->ofTenant($workspace)->delete();
    }
}
```

Workflows remember who saved them last as an e-mail address in `created_by` and `updated_by`. A deleted person's address stays there until the workspace goes; clear it in a listener of `App\Events\AccountDeleting` if your privacy policy promises otherwise.

## 7. Test it

`tests/Feature/Modules/FlowTest.php`:

```php file=tests/Feature/Modules/FlowTest.php
<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Schema;
use Packstub\Features\Facades\Features;
use Packstub\Flow\FlowPlugin;
use Packstub\Flow\Models\Workflow;

use function Pest\Laravel\actingAs;

function workflowFor(Workspace $workspace): Workflow
{
    return Workflow::query()->withoutGlobalScopes()->forceCreate([
        'name' => 'Welcome',
        'tenant_type' => $workspace->getMorphClass(),
        'tenant_id' => (string) $workspace->id,
    ]);
}

it('shows each workspace its own workflows', function (string $role) {
    $workspace = workspace('pro');
    workflowFor($workspace);
    workflowFor(workspace('pro'))->forceFill(['name' => 'Not yours'])->save();

    actingAs(memberOf($workspace, $role))
        ->get("/app/{$workspace->slug}/workflows")
        ->assertOk()
        ->assertSee('Welcome')
        ->assertDontSee('Not yours');
})->with(['owner', 'admin', 'member']);

it('takes the number of workflows from the plan', function (string $plan, ?int $limit) {
    $workspace = workspace($plan);
    workflowFor($workspace);

    inWorkspace($workspace);

    expect(FlowPlugin::get()->getMaxWorkflows($workspace))->toBe($limit)
        ->and(Features::used('workflows', $workspace))->toBe(1);
})->with([
    ['free', 1],
    ['pro', 25],
    ['business', null],
]);

it('deletes the workflows of a deleted workspace, and nothing of another', function () {
    $workspace = workspace();
    workflowFor($workspace);
    $theirs = workflowFor(workspace());

    $workspace->delete();

    expect(Workflow::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$theirs->id])
        ->and(Schema::hasTable('flow_workflow_runs'))->toBeTrue();
});
```

```bash run
composer lint
composer test
```

## A database per workspace later

Keep Flow's tables central, with the published migration among the central ones: a workflow finds its workspace through `tenant_type` and `tenant_id`, global workflows run for every workspace, and Flow's dispatcher reads them outside any workspace's connection. What a workflow reads or writes about a project then runs inside that workspace: give Flow a resolver that does it (`Flow::resolveTenantUsing()`, [Tenancy](https://github.com/packstub/filament-flow/blob/main/docs/tenancy.md)).

## Removing it

Delete the plugin lines and imports from `app/Filament/Plugins.php`, `HasWorkflows` from `Project`, the `workflows` feature, plan values and `usage()` line, `PACKSTUB_FLOW_TENANT_RELATIONSHIP`, `app/Listeners/DeleteWorkspaceWorkflows.php`, `tests/Feature/Modules/FlowTest.php` and the seven lines in `centralTables()`, then:

```bash
composer remove packstub/filament-flow
```

Drop the seven tables with a migration of your own and delete the published `create_flow_tables` migration.
