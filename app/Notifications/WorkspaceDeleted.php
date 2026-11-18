<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To everyone who was in a workspace when it was deleted. The workspace is
 * gone by the time it is sent, so it carries only names, never the models.
 */
class WorkspaceDeleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $workspace,
        public ?string $deletedBy,
    ) {}

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
            ->subject("{$this->workspace} was deleted")
            ->markdown('mail.workspace-deleted', [
                'workspace' => $this->workspace,
                'deletedBy' => $this->deletedBy,
            ]);
    }
}
