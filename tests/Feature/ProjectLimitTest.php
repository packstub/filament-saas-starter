<?php

use App\Enums\ProjectStatus;
use App\Filament\App\Resources\Projects\Pages\ManageProjects;
use App\Models\Project;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Packstub\Features\Facades\Features;

use function Pest\Livewire\livewire;

it('creates projects in the current workspace', function () {
    $workspace = workspace('free');

    inWorkspace($workspace);

    livewire(ManageProjects::class)
        ->callAction('create', ['name' => 'Website relaunch', 'status' => ProjectStatus::Paused])
        ->assertHasNoActionErrors();

    $project = $workspace->projects()->sole();

    expect($project->name)->toBe('Website relaunch')
        ->and($project->status)->toBe(ProjectStatus::Paused);
});

it('starts a project as active and refuses a status that does not exist', function () {
    inWorkspace($workspace = workspace('free'));

    livewire(ManageProjects::class)
        ->callAction('create', ['name' => 'Website relaunch'])
        ->assertHasNoActionErrors()
        ->callAction('create', ['name' => 'Mobile app', 'status' => 'archived'])
        ->assertHasActionErrors(['status']);

    expect($workspace->projects()->sole()->status)->toBe(ProjectStatus::Active);
});

it('shows the status of each project as a badge with its label', function () {
    $workspace = workspace('pro');
    Project::factory()->for($workspace)->paused()->create();
    Project::factory()->for($workspace)->done()->create();

    inWorkspace($workspace);

    livewire(ManageProjects::class)
        ->assertSee(['Paused', 'Done'])
        ->assertDontSee('Active');

    expect(ProjectStatus::Paused->getColor())->toBe('warning');
});

it('shows a workspace only its own projects', function () {
    $workspace = workspace();
    $mine = Project::factory()->for($workspace)->create();
    $theirs = Project::factory()->create();

    inWorkspace($workspace);

    livewire(ManageProjects::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('stops at the project limit of the plan', function () {
    $workspace = workspace('free');
    Project::factory()->count(3)->for($workspace)->create();

    inWorkspace($workspace);

    expect(Features::limit('projects'))->toBe(3)
        ->and(Features::canUse('projects'))->toBeFalse();

    livewire(ManageProjects::class)
        ->assertSee('3 of 3 projects on the Free plan')
        ->assertActionHidden('create')
        ->assertActionVisible('upgrade');

    expect($workspace->projects()->count())->toBe(3);
});

it('refuses a project that would go over the limit when the form was already open', function () {
    $workspace = workspace('free');
    Project::factory()->count(2)->for($workspace)->create();

    inWorkspace($workspace);

    $page = livewire(ManageProjects::class)->mountAction('create');

    // A colleague takes the last place while the form is open.
    Project::factory()->for($workspace)->create();

    $page->fillForm(['name' => 'One too many'])->callMountedAction();

    expect($workspace->projects()->count())->toBe(3)
        ->and($workspace->projects()->where('name', 'One too many')->exists())->toBeFalse();
});

it('checks the limit again while saving, under the lock', function () {
    $workspace = workspace('free');
    Project::factory()->count(3)->for($workspace)->create();

    inWorkspace($workspace);

    // The policy lets it through, as it did a moment earlier for a request racing this one:
    // only the check under the lock stands between the workspace and a fourth project.
    Gate::before(fn (): bool => true);

    livewire(ManageProjects::class)
        ->callAction('create', ['name' => 'One too many'])
        ->assertNotified();

    expect($workspace->projects()->count())->toBe(3);
});

it('refuses a project over the limit however the action is called', function () {
    $workspace = workspace('free');
    Project::factory()->count(3)->for($workspace)->create();

    inWorkspace($workspace);

    // Asked for by the browser all the same, the hidden action does not open.
    livewire(ManageProjects::class)
        ->assertActionHidden('create')
        ->call('mountAction', 'create')
        ->assertActionNotMounted();

    expect($workspace->projects()->count())->toBe(3);
});

it('asks to try again when someone else is saving a project at the same moment', function () {
    $workspace = workspace('free');

    inWorkspace($workspace);

    $lock = Cache::lock('projects:'.$workspace->getKey(), 10);
    $lock->get();

    livewire(ManageProjects::class)
        ->callAction('create', ['name' => 'Held up'])
        ->assertNotified('Someone else is adding a project right now');

    $lock->release();

    expect($workspace->projects()->count())->toBe(0);
});

it('gives projects a ULID key', function () {
    expect(Str::isUlid(Project::factory()->create()->id))->toBeTrue();
});

it('makes room when the workspace moves to a bigger plan', function () {
    $workspace = workspace('free');
    Project::factory()->count(3)->for($workspace)->create();

    $workspace->changePlan('pro');

    inWorkspace($workspace);

    livewire(ManageProjects::class)
        ->assertActionVisible('create')
        ->callAction('create', ['name' => 'Fourth project'])
        ->assertHasNoActionErrors();

    expect($workspace->projects()->count())->toBe(4)
        ->and(Features::limit('projects'))->toBe(50);
});

it('keeps what a workspace has when it moves to a smaller plan', function () {
    $workspace = workspace('pro');
    $projects = Project::factory()->count(5)->for($workspace)->create();

    $workspace->changePlan('free');

    inWorkspace($workspace);

    // Over the limit: nothing new, and everything there can still be changed or removed.
    livewire(ManageProjects::class)
        ->assertSee('5 of 3 projects on the Free plan')
        ->assertActionHidden('create')
        ->assertActionVisible('upgrade')
        ->assertCanSeeTableRecords($projects)
        ->callAction(TestAction::make('edit')->table($projects[0]), ['name' => 'Renamed'])
        ->assertHasNoActionErrors()
        ->callAction(TestAction::make(DeleteAction::class)->table($projects[1]));

    expect($projects[0]->fresh()->name)->toBe('Renamed')
        ->and($workspace->projects()->count())->toBe(4);
});

it('offers the upgrade only to those who may open Billing', function () {
    $workspace = workspace('free');
    Project::factory()->count(3)->for($workspace)->create();

    inWorkspace($workspace, memberOf($workspace, 'member'));

    livewire(ManageProjects::class)
        ->assertActionHidden('create')
        ->assertActionHidden('upgrade');
});

it('shows no limit on a plan without one', function () {
    inWorkspace(workspace('business'));

    livewire(ManageProjects::class)
        ->assertDontSee('projects on the Business plan')
        ->assertActionVisible('create');
});
