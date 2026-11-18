<?php

namespace App\Listeners;

use App\Events\AccountDeleting;
use App\Notifications\AccountDeleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Confirm a deleted account to its address, once the deletion is committed.
 */
class SendAccountDeletedNotice
{
    public function handle(AccountDeleting $event): void
    {
        $email = $event->user->email;
        $name = $event->user->name;
        $locale = $event->user->preferredLocale();

        DB::afterCommit(fn () => Notification::route('mail', [$email => $name])->notify((new AccountDeleted($name))->locale($locale)));
    }
}
