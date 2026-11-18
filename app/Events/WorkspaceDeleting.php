<?php

namespace App\Events;

use App\Models\Workspace;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace is being deleted: remove what it owns in tables the kit does
 * not know about (a module's, say) in a listener of this event.
 *
 * Dispatched first in Workspace's `deleting` hook, inside the transaction
 * that deletes the workspace: before its Stripe subscription is cancelled
 * and before the kit removes its own rows, so the projects are still there
 * to read and a listener that fails stops the delete before Stripe is
 * asked anything. Listen synchronously: a queued listener would run after
 * the workspace is gone, and outside the transaction. An exception in a
 * listener stops the delete and rolls everything back.
 *
 * Do in the transaction only what it can undo: database rows. Deleting
 * files or calling another service goes in DB::afterCommit(), so a
 * deletion that is rolled back leaves them as they were. A listener that
 * dispatches a job should hand it ids, never the models being deleted.
 */
final class WorkspaceDeleting
{
    use Dispatchable;

    public function __construct(public readonly Workspace $workspace) {}
}
