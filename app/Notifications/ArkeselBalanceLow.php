<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ArkeselBalanceLow extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $providerMessage) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Arkesel SMS balance is too low to send messages')
            ->greeting('Heads up!')
            ->line('An attempt to send an SMS via Arkesel failed because the account is out of balance or otherwise rejected.')
            ->line('Provider response: '.$this->providerMessage)
            ->line('SMS notifications (registration confirmations, attendance confirmations, reminders) will keep failing until the Arkesel account balance is topped up. Email notifications are unaffected.')
            ->line('This alert will not repeat for a few hours even if more SMS sends fail, to avoid flooding your inbox during a bulk send.');
    }
}
