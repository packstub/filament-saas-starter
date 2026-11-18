<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Workspace;
use Packstub\AccountSwitcher\Facades\AccountSwitcher;

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
     * Downloading everything the workspace holds, members' e-mail addresses
     * included, is for its owners only, and never for support signed in as
     * one (a support login).
     */
    public function export(User $user, Workspace $workspace): bool
    {
        return $user->ownsTeam($workspace) && ! AccountSwitcher::isImpersonating();
    }

    /**
     * Deleting the workspace, with everything in it, is for its owners only,
     * and never for support signed in as one.
     */
    public function delete(User $user, Workspace $workspace): bool
    {
        return $user->ownsTeam($workspace) && ! AccountSwitcher::isImpersonating();
    }
}
