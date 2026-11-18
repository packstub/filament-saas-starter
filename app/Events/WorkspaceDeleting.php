<?php

namespace App\Events;

use App\Models\Workspace;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A workspace is being deleted: remove what it owns in tables the kit does
 * not know about (a module's, say) in a listener of this event.
 *
 * Dispatched from Workspace's `deleting` hook, inside the transaction that
 * deletes the workspace, after its Stripe subscription is cancelled and
 * before the kit removes its own rows, so the projects are still there to
 * read. Listen synchronously: a queued listener would run after the
 * workspace is gone, and outside the transaction. An exception in a
 * listener stops the delete and rolls everything back.
 */
final class WorkspaceDeleting
{
    use Dispatchable;

    public function __construct(public readonly Workspace $workspace) {}
}
