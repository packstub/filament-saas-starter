# A Projects board with Kanban

[Kanban](https://github.com/packstub/filament-kanban) adds a **Board** next to the Projects table: one column per `ProjectStatus` (Active, Paused, Done), cards that move with drag and drop, and a **Board** / **Table** switch above both. It reuses what the kit has: the resource's query keeps the board inside the current workspace, and `ProjectPolicy::update()` decides who may move a card.

## What you need

- `packstub/filament-kanban` 1.0, **a beta** on Packagist for now (`v1.0.0-beta.1`), PHP 8.3+. The kit's `minimum-stability` stays `stable`: the `@beta` flag below allows a beta of this one package only. Change the constraint to `^1.0` once 1.0.0 is out.
- No tables, no config, no queue.

## 1. Install

```bash run
composer require "packstub/filament-kanban:^1.0@beta"
```

Its script and stylesheet are Filament assets, published by `php artisan filament:assets`, which `composer install` and `composer update` already run (`post-autoload-dump`).

## 2. The board page

Create `app/Filament/App/Resources/Projects/Pages/ProjectBoard.php`:

```php file=app/Filament/App/Resources/Projects/Pages/ProjectBoard.php
<?php

namespace App\Filament\App\Resources\Projects\Pages;

use App\Enums\ProjectStatus;
use App\Filament\App\Resources\Projects\ProjectResource;
use App\Models\Project;
use Packstub\Kanban\Board;
use Packstub\Kanban\Card;
use Packstub\Kanban\Pages\KanbanResourcePage;

/**
 * The workspace's projects by status. The cards come from
 * ProjectResource::getEloquentQuery(), so the board shows the current
 * workspace only, and a move needs ProjectPolicy::update().
 */
class ProjectBoard extends KanbanResourcePage
{
    protected static string $resource = ProjectResource::class;

    protected static ?string $title = 'Projects board';

    public function kanban(Board $board): Board
    {
        return $board
            ->columns(ProjectStatus::class)
            ->card(fn (Project $project): Card => Card::make()->title($project->name));
    }
}
```

Register it in `app/Filament/App/Resources/Projects/ProjectResource.php`:

```php use=app/Filament/App/Resources/Projects/ProjectResource.php
use App\Filament\App\Resources\Projects\Pages\ProjectBoard;
```

```php in=app/Filament/App/Resources/Projects/ProjectResource.php after="'index' => ManageProjects::route('/'),"
'board' => ProjectBoard::route('/board'),
```

and put the **Board** button above the table, in `getHeaderActions()` of `app/Filament/App/Resources/Projects/Pages/ManageProjects.php`:

```php use=app/Filament/App/Resources/Projects/Pages/ManageProjects.php
use Packstub\Kanban\Actions\KanbanAction;
```

```php in=app/Filament/App/Resources/Projects/Pages/ManageProjects.php after="function getHeaderActions()|return ["
KanbanAction::make(),
```

The board page shows a **Table** button back. Columns, colours and labels come from `ProjectStatus` (`HasLabel`, `HasColor`); a new case is a new column.

## 3. Plans, deletion and tables

Nothing to add. Projects already count against the plan's `projects` limit, the board creates no projects (only `ManageProjects` and the API do, through `App\Actions\CreateProject`), and Kanban has no tables of its own: a person's hidden columns are kept in their browser.

To create projects from a column, use `Column::creatable()` with a `createUsing()` that calls `CreateProject::in(Filament::getTenant(), $data)`, so the limit is checked under the same lock. See [Columns](https://github.com/packstub/filament-kanban/blob/main/docs/columns.md#creating-in-a-column).

## 4. Test it

`tests/Feature/Modules/KanbanTest.php`:

```php file=tests/Feature/Modules/KanbanTest.php
<?php

use App\Enums\ProjectStatus;
use App\Filament\App\Resources\Projects\Pages\ProjectBoard;
use App\Models\Project;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('shows the workspace\'s projects on a board, and nobody else\'s', function () {
    $workspace = workspace();
    Project::factory()->for($workspace)->create(['name' => 'Launch', 'status' => ProjectStatus::Paused]);
    Project::factory()->for(workspace())->create(['name' => 'Not yours']);

    actingAs(memberOf($workspace, 'member'))
        ->get("/app/{$workspace->slug}/projects/board")
        ->assertOk()
        ->assertSee('Launch')
        ->assertDontSee('Not yours');

    actingAs(User::factory()->create())
        ->get("/app/{$workspace->slug}/projects/board")
        ->assertNotFound();
});

it('changes a project\'s status when its card moves', function () {
    $workspace = workspace();
    $project = Project::factory()->for($workspace)->create(['status' => ProjectStatus::Active]);

    inWorkspace($workspace, memberOf($workspace, 'member'));

    livewire(ProjectBoard::class)
        ->call('kanbanMove', (string) $project->id, ProjectStatus::Done->value)
        ->assertReturned(fn (array $result): bool => $result['ok'] === true);

    expect($project->fresh()->status)->toBe(ProjectStatus::Done);
});

it('never moves a card of another workspace', function () {
    $workspace = workspace();
    $theirs = Project::factory()->for(workspace())->create(['status' => ProjectStatus::Active]);

    inWorkspace($workspace);

    livewire(ProjectBoard::class)
        ->call('kanbanMove', (string) $theirs->id, ProjectStatus::Done->value)
        ->assertReturned(fn (array $result): bool => $result['ok'] === false);

    expect($theirs->fresh()->status)->toBe(ProjectStatus::Active);
});
```

```bash run
composer lint
composer test
```

## A database per workspace later

Nothing changes: the board reads projects through the resource, which then reads the workspace's own database.

## Removing it

Delete `ProjectBoard.php`, its route in `ProjectResource::getPages()`, `KanbanAction::make()` and its import in `ManageProjects`, and `tests/Feature/Modules/KanbanTest.php`, then:

```bash
composer remove packstub/filament-kanban
```
