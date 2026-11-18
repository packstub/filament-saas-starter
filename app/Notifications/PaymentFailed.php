<?php

namespace App\Notifications;

use App\Filament\App\Pages\Billing;
use App\Models\Workspace;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a workspace's owners when Stripe could not collect a payment and the
 * subscription fell past due: by e-mail and in the bell menu, with a link
 * to the Billing page. Stripe keeps retrying; the plan stays while it does.
 */
class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Workspace $workspace) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('mail.payment_failed.subject', ['workspace' => $this->workspace->name]))
            ->markdown('mail.payment-failed', [
                'workspace' => $this->workspace->name,
                'logo' => $this->workspace->getFilamentAvatarUrl(),
                'url' => $this->billingUrl(),
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return FilamentNotification::make()
            ->danger()
            ->title(__('mail.payment_failed.database_title', ['workspace' => $this->workspace->name]))
            ->body(__('mail.payment_failed.database_body'))
            ->actions([
                Action::make('billing')->label(__('mail.payment_failed.database_action'))->url($this->billingUrl())->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    protected function billingUrl(): string
    {
        return Billing::getUrl(panel: 'app', tenant: $this->workspace);
    }
}
