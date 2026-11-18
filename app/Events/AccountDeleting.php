<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Someone is deleting their account (User::deleteAccount()): remove what a
 * module keeps about them (their recordings, their preferences) in a
 * listener of this event.
 *
 * Dispatched inside the transaction that deletes the account, after the
 * workspaces only they used are deleted and before their own row goes.
 * Listen synchronously, as for WorkspaceDeleting: an exception stops the
 * deletion and rolls everything back. Files and calls to other services go
 * in DB::afterCommit(), as there.
 */
final class AccountDeleting
{
    use Dispatchable;

    public function __construct(public readonly User $user) {}
}
