<?php

use App\Filament\App\Resources\Projects\Pages\ManageProjects;
use App\Models\Project;
use App\Models\User;
use App\Support\WorkspacePermissions;
use Filament\Actions\DeleteAction;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\Testing\TestAction;
use Packstub\Features\Facades\Features;
use Packstub\Teams\Facades\Teams;

use function Pest\Laravel\actingAs;
use function Pest\Livewire\livewire;

it('shows the projects to every member of the workspace', function (string $role) {
    $workspace = workspace('pro');

    actingAs(memberOf($workspace, $role))
        ->get("/app/{$workspace->slug}/projects")
        ->assertOk();
})->with(['owner', 'admin', 'member']);

it('hides a workspace and its projects from someone who is not a member', function () {
    $workspace = workspace('pro');
    Project::factory()->for($workspace)->create(['name' => 'Secret launch']);

    actingAs(User::factory()->create())
        ->get("/app/{$workspace->slug}/projects")
        ->assertNotFound()
        ->assertDontSee('Secret launch');
});

it('decides what a member may do with a project from their role', function (string $role, bool $create, bool $update, bool $delete) {
    $workspace = workspace('pro');
    $project = Project::factory()->for($workspace)->create();

    $user = inWorkspace($workspace, memberOf($workspace, $role));

    expect($user->can('create', Project::class))->toBe($create)
        ->and($user->can('view', $project))->toBeTrue()
        ->and($user->can('update', $project))->toBe($update)
        ->and($user->can('delete', $project))->toBe($delete);

    livewire(ManageProjects::class)
        ->assertActionVisible('create')
        ->assertActionVisible(TestAction::make('edit')->table($project))
        ->{$delete ? 'assertActionVisible' : 'assertActionHidden'}(TestAction::make(DeleteAction::class)->table($project));
})->with([
    'owner' => ['owner', true, true, true],
    'admin' => ['admin', true, true, true],
    'member' => ['member', true, true, false],
]);

it('lets an owner or admin delete a project, and not a member', function (string $role, bool $deleted) {
    $workspace = workspace('pro');
    $project = Project::factory()->for($workspace)->create();

    inWorkspace($workspace, memberOf($workspace, $role));

    $page = livewire(ManageProjects::class);

    if ($deleted) {
        $page->callAction(TestAction::make(DeleteAction::class)->table($project));
    } else {
        // Asked for by the browser all the same, the hidden action does not open.
        $page->call('mountAction', 'delete', [], ['table' => true, 'recordKey' => $project->getKey()])
            ->assertActionNotMounted();
    }

    expect($project->fresh() === null)->toBe($deleted);
})->with([
    'owner' => ['owner', true],
    'admin' => ['admin', true],
    'member' => ['member', false],
]);

it('gives nobody a say over the projects of a workspace they are not in', function (string $ability) {
    $project = Project::factory()->create();
    $outsider = inWorkspace(workspace('pro'));

    expect($outsider->can($ability, $project))->toBeFalse();
})->with(['view', 'update', 'delete']);

it('takes the role from the workspace of the project, not from another one', function () {
    // An admin elsewhere, a member here.
    $here = workspace('pro');
    $elsewhere = workspace('pro');
    $project = Project::factory()->for($here)->create();
    $other = Project::factory()->for($elsewhere)->create();
    $user = memberOf($here, 'member');
    Teams::addMember($elsewhere, $user, 'admin');

    inWorkspace($here, $user);

    expect($user->can('delete', $project))->toBeFalse()
        ->and($user->can('delete', $other))->toBeTrue();

    livewire(ManageProjects::class)
        ->assertActionHidden(TestAction::make(DeleteAction::class)->table($project));
});

it('leaves the projects of another workspace alone, whatever the action is given', function (string $action, array $data) {
    $theirs = Project::factory()->create(['name' => 'Theirs']);

    inWorkspace(workspace('pro'));

    $page = livewire(ManageProjects::class)->assertCanNotSeeTableRecords([$theirs]);

    // The table is scoped to the workspace, so the action finds no such record to work on.
    expect(fn () => $page->callAction(TestAction::make($action)->table($theirs), $data))
        ->toThrow(ActionNotResolvableException::class);

    expect($theirs->fresh()?->name)->toBe('Theirs');
})->with([
    'edit' => ['edit', ['name' => 'Taken over']],
    'delete' => [DeleteAction::class, []],
]);

it('asks the database the same number of questions however many projects are listed', function () {
    $workspace = workspace('business');
    $user = memberOf($workspace, 'admin');

    $render = function () use ($workspace, $user): int {
        // As every request starts: nothing remembered about the member's role or the workspace's plan.
        inWorkspace($workspace, $user);
        app(WorkspacePermissions::class)->forget();
        Teams::forgetRoles();
        Features::flush($workspace);
        Features::forgetAllUsage();

        return countQueries(fn () => livewire(ManageProjects::class)->assertOk());
    };

    Project::factory()->count(2)->for($workspace)->create();
    $few = $render();

    Project::factory()->count(6)->for($workspace)->create();
    $many = $render();

    expect($many)->toBe($few);
});

it('follows a role that changes while the same person stays signed in', function () {
    $workspace = workspace('pro');
    $project = Project::factory()->for($workspace)->create();
    $user = inWorkspace($workspace, memberOf($workspace, 'member'));

    expect($user->can('delete', $project))->toBeFalse();

    Teams::changeRole($workspace, $user, 'admin');

    expect($user->can('delete', $project))->toBeTrue();

    Teams::removeMember($workspace, $user);

    expect($user->can('delete', $project))->toBeFalse()
        ->and($user->can('view', $project))->toBeFalse();

    Teams::addMember($workspace, $user, 'member');

    expect($user->can('view', $project))->toBeTrue();

    // Ownership handed over: the new owner may delete at once.
    Teams::transferOwnership($workspace, Teams::ownersOf($workspace)->first(), $user);

    expect($user->can('delete', $project))->toBeTrue();
});
