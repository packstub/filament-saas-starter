<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;

class WorkspacePolicy
{
    /**
     * Renaming the workspace (the "Workspace settings" page) is for its owners and admins.
     */
    public function update(User $user, Workspace $workspace): bool
    {
        return $user->canManageTeam($workspace);
    }

    /**
     * Deleting the workspace, with everything in it, is for its owners only.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->ownsTeam($workspace);
    }
}
