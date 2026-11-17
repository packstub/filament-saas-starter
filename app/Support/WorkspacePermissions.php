<?php

namespace App\Support;

use App\Models\User;
use App\Models\Workspace;

/**
 * What a member may do in a workspace, looked up once per request: a table
 * asks the policy for every row and every action. One instance lives for one
 * request or queued job (it is `scoped` in AppServiceProvider), and
 * App\Listeners\ForgetWorkspacePermissions empties it whenever a membership
 * or a role changes, so an answer never outlives the role it came from.
 */
final class WorkspacePermissions
{
    /** @var array<string, bool> */
    private array $answers = [];

    public function allows(User $user, Workspace $workspace, string $permission): bool
    {
        return $this->answers["{$user->getKey()}:{$workspace->getKey()}:{$permission}"] ??= $user->hasTeamPermission($workspace, $permission);
    }

    public function forget(): void
    {
        $this->answers = [];
    }
}
