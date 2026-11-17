<?php

namespace App\Listeners;

use App\Support\WorkspacePermissions;
use Packstub\Teams\Events\MemberAdded;
use Packstub\Teams\Events\MemberRemoved;
use Packstub\Teams\Events\MemberRoleChanged;
use Packstub\Teams\Events\OwnershipTransferred;

/**
 * A membership or a role changed: what was remembered about permissions
 * during this request (App\Support\WorkspacePermissions) no longer holds.
 */
class ForgetWorkspacePermissions
{
    public function __construct(private WorkspacePermissions $permissions) {}

    public function handle(MemberAdded|MemberRemoved|MemberRoleChanged|OwnershipTransferred $event): void
    {
        $this->permissions->forget();
    }
}
