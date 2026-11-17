<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Packstub\Features\Facades\Features;

/**
 * Who may do what with projects comes from the member's role in the project's
 * workspace (the permissions of each role are in config/packstub-teams.php).
 * Creating one also needs room in the plan.
 */
class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->canInWorkspace($project->workspace, 'read');
    }

    public function create(User $user): bool
    {
        $workspace = Features::currentScope();

        return $workspace !== null
            && $user->canInWorkspace($workspace, 'create')
            && Features::canUse('projects', $workspace);
    }

    public function update(User $user, Project $project): bool
    {
        return $user->canInWorkspace($project->workspace, 'update');
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->canInWorkspace($project->workspace, 'delete');
    }
}
