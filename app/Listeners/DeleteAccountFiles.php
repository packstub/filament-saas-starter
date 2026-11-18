<?php

namespace App\Listeners;

use App\Events\AccountDeleting;
use App\Support\Uploads;
use Illuminate\Support\Facades\DB;

/**
 * A deleted account's uploaded files (the profile picture, and whatever else
 * is kept in its folder) go with it, once the deletion is committed.
 */
class DeleteAccountFiles
{
    public function handle(AccountDeleting $event): void
    {
        $directory = Uploads::userDirectory($event->user);

        DB::afterCommit(fn () => Uploads::disk()->deleteDirectory($directory));
    }
}
