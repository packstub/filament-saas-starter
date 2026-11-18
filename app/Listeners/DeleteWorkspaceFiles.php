<?php

namespace App\Listeners;

use App\Events\WorkspaceDeleting;
use App\Support\Uploads;
use Illuminate\Support\Facades\DB;

/**
 * A deleted workspace's uploaded files (its logo, and whatever else is kept
 * in its folder) go with it, once the deletion is committed: a deletion
 * that rolls back keeps them.
 */
class DeleteWorkspaceFiles
{
    public function handle(WorkspaceDeleting $event): void
    {
        $directory = Uploads::workspaceDirectory($event->workspace);

        DB::afterCommit(fn () => Uploads::disk()->deleteDirectory($directory));
    }
}
