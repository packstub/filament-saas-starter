<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The confirmation that an account was deleted, sent to its address after
 * the account is gone, so it carries only the name.
 */
class AccountDeleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $name) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your '.config('app.name').' account was deleted')
            ->markdown('mail.account-deleted', ['name' => $this->name]);
    }
}
